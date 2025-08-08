from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Dict, List, Any
import pandas as pd
from train_merge_model import get_ml_models, merge_rows 
from resolveDiffOrderID import merge_rows_diff_order 

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Change to frontend URL in production
    allow_credentials=True,
    allow_methods=["*"],  # Allow all HTTP methods
    allow_headers=["*"],  # Allow all headers
)


# ✅ Cache for lazy loading
ml_model_cache = {
    "refund_model": None,
    "payout_model": None,
    "refund_encoders": None,
    "payout_encoders": None,
    "merged_models": None,
}



# ✅ Define the expected request format
class DuplicateRequest(BaseModel):
    fully_identical_rows: List[Dict[str, Any]]
    same_order_id_diff_attributes: List[Dict[str, Any]]
    same_attributes_diff_order_id: List[Dict[str, Any]]




def detect_report_type(df):
    """Identify report type based on column names"""
    refund_columns = {"return_request_date", "refund_amount", "return_status"}
    payout_columns = {"transaction_date", "gross_sales_amount", "net_payout_amount"}

    df_columns = set(df.columns)

    if refund_columns.issubset(df_columns):
        return "refund"
    elif payout_columns.issubset(df_columns):
        return "payout"
    return "unknown"  # Default if neither matches



def resolve_fully_identical(df):
    """Find and remove fully identical rows while keeping the correct row."""
    
    df.columns = [col.strip().lower().replace(" ", "_") for col in df.columns]  # Normalize column names

    # ✅ Convert numeric & date values properly
    for col in df.columns:
        if df[col].dtype == "object":
            df[col] = pd.to_numeric(df[col], errors="ignore")
            df[col] = pd.to_datetime(df[col], errors="ignore")

    # ✅ Ignore "row" column when checking for duplicates
    cols_to_check = [col for col in df.columns if col != "row"]

    # Find fully identical duplicates
    duplicate_groups = df[df.duplicated(subset=cols_to_check, keep=False)]
    
    rows_to_remove = []
    
    # ✅ Dynamically determine columns for sorting (numeric & datetime)
    sort_columns = [col for col in df.columns if df[col].dtype in ["int64", "float64", "datetime64[ns]"]]

    for _, group in duplicate_groups.groupby(cols_to_check):
        if sort_columns:
            # ✅ Sort dynamically based on available numeric & date columns
            sorted_group = group.sort_values(by=sort_columns, ascending=[False] * len(sort_columns))
        else:
            # ✅ If no meaningful sorting columns, keep the first occurrence
            sorted_group = group

        correct_row_index = sorted_group.iloc[0]["row"]  # Always keep the first in sorted group

        for idx in group["row"]:
            if idx != correct_row_index:
                rows_to_remove.append({"row": idx, "action": "remove"})

    return rows_to_remove




#def resolve_same_order_id(df):
    """Merge rows with the same order ID but different attributes."""
    resolutions = []
    if "order_id" in df.columns:
        grouped = df.groupby("order_id")
        for _, group in grouped:
            if len(group) > 1:
                for _, row in group.iterrows():
                    resolutions.append({"row": row["row"], "action": "merge"})
    return resolutions



def resolve_same_order_id(df, report_type):
    """Merge rows with the same order ID using ML (Refund & Payout handled separately)"""
    resolutions = []
    
    # ✅ Ensure "row" exists before using it
    if "row" not in df.columns:
        df.insert(0, "row", range(1, len(df) + 1))  # Auto-generate row numbers if missing

    if "order_id" in df.columns:
        grouped = df.groupby("order_id")
        
        #models = train_and_update_models()  # ✅ Call the function to get the models dictionary
        models = get_ml_models() 

        for _, group in grouped:
            if len(group) > 1:
                if report_type == "refund":
                    #group["return_status"] = group["return_status"].astype("category").cat.codes
                    status_mapping = models.get("status_mapping", {"Pending": 0, "Approved": 1, "Completed": 2})
                    group["return_status"] = group["return_status"].map(status_mapping).fillna(-1).astype(int)

                    X_new = group[["return_status", "refund_amount"]]
                    predicted_merge_decision = models["refund_model"].predict(X_new)[0]  # ✅ FIXED
                    merge_decision = models["refund_mapping"].get(predicted_merge_decision, "keep_latest")
                elif report_type == "payout":
                    X_new = group[["gross_sales_amount", "refunds_issued", "net_payout_amount"]]
                    predicted_merge_decision = models["payout_model"].predict(X_new)[0]  # ✅ FIXED
                    merge_decision = models["payout_mapping"].get(predicted_merge_decision, "keep_latest")
                else:
                    continue  # Skip unknown report types

                merged_row = merge_rows(group, report_type).to_dict()

                #resolutions.append({
                #    "row": merged_row.get("row", None),
                #    "action": "merge",
                #    "merged_data": merged_row
                #})
                merged_row["action"] = "merge"
                merged_row["row"] = merged_row.get("row", None)
                resolutions.append(merged_row)

    return resolutions



def resolve_diff_order_same_attributes(df, report_type):
    """Handle cases where same attributes exist but with different Order IDs."""
    print(" 2 rspy report_type:", report_type)
    resolutions = []
    
    # Ensure "row" exists before using it
    if "row" not in df.columns:
        df.insert(0, "row", range(1, len(df) + 1))  # Auto-generate row numbers if missing
    
    # Process and return the DataFrame based on report_type
    merged_df = merge_rows_diff_order(df, report_type)

    for _, group in merged_df.groupby("order_id"):  # ✅ Column name should be lowercase
        merged_row_diff = group.iloc[0].to_dict()  # ✅ Extract first row after merging
        #resolutions.append({
        #    "row": merged_row_diff.get("row", None),
        #    "action": "merge",
        #    "merged_data": merged_row_diff
        #})
        merged_row_diff["action"] = "merge"
        merged_row_diff["row"] = merged_row_diff.get("row", None)
        resolutions.append(merged_row_diff)
    
    return resolutions



def resolve_duplicates(category, rows, report_type=None):

    """Apply different resolution strategies based on category."""
    if not rows:
        return []

    df = pd.DataFrame(rows)
    df.columns = df.columns.str.strip().str.lower().str.replace(" ", "_")

    if category == "fully_identical_rows":
        return resolve_fully_identical(df)
    
    elif category == "same_order_id_diff_attributes":
        report_type = detect_report_type(df)  # 🔹 Detect whether it's "refund" or "payout"
        return resolve_same_order_id(df, report_type)

    elif category == "same_attributes_diff_order_id":
        report_type = detect_report_type(df)
        print(" 1 rspy report_type:", report_type)
        return resolve_diff_order_same_attributes(df, report_type)  

    return [{"row": row["row"], "action": "review"} for _, row in df.iterrows()]



def normalize_keys(data):
    """Convert dictionary keys to lowercase with underscores"""
    if isinstance(data, dict):
        return {k.lower().replace(" ", "_"): normalize_keys(v) for k, v in data.items()}
    elif isinstance(data, list):
        return [normalize_keys(i) for i in data]
    return data


@app.post("/resolve_duplicates")
async def resolve(request: DuplicateRequest):
    input_data = request.dict()
    print("📥 Incoming Request Data to /resolve_duplicates:", input_data) 
    
    resolved_data = {}

    for category, rows in input_data.items():
        if not rows:  
            print(f"⚠️ No data found for category {category}")  # ✅ Debugging step
            continue 

        df = pd.DataFrame(rows)
        report_type = detect_report_type(df) if category == "same_order_id_diff_attributes" else None
        resolved_data[category] = resolve_duplicates(category, rows, report_type)

    input_data = normalize_keys(input_data) 

    print("📩 Received Data in FastAPI:", input_data)

    print("📤 Resolved Data:", resolved_data) 

    return {"resolved": resolved_data}


@app.get("/")
async def home():
    return {"message": "FastAPI Server is Running!"}


if __name__ == '__main__':
    import uvicorn
    print("FastAPI is running on port 8000...")
    uvicorn.run(app, host="127.0.0.1", port=8000, reload=True)

