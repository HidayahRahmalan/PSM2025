import os
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestClassifier
import re


#find latest historical desicion file
def find_latest_file(directory, filename_prefix):
    """Find the latest modified file matching the given prefix and supported formats."""
    supported_formats = [".csv", ".xls", ".xlsx"]
    latest_file = None
    latest_mtime = 0

    for file in os.listdir(directory):
        if file.startswith(filename_prefix) and file.endswith(tuple(supported_formats)):
            full_path = os.path.join(directory, file)
            file_mtime = os.path.getmtime(full_path)  # Get last modified time
            if file_mtime > latest_mtime:
                latest_mtime = file_mtime
                latest_file = full_path

    if latest_file:
        print(f"Loading file: {latest_file}")  # Show which file is loaded
        return latest_file
    else:
        raise FileNotFoundError(f"No valid file found for {filename_prefix} in {directory}")



#def load_cleaned_data():
    """Load the latest cleaned refund and payout reports."""
    directory = r"C:\xampp\htdocs\fyp\cleaned"
    
    # Find the latest cleaned files
    refund_file = find_latest_file(directory, "c xlsx Refund Report")
    payout_file = find_latest_file(directory, "c xlsx Payout Report")
    
    # Load data based on file type
    refund_df = pd.read_excel(refund_file) if refund_file.endswith((".xls", ".xlsx")) else pd.read_csv(refund_file)
    payout_df = pd.read_excel(payout_file) if payout_file.endswith((".xls", ".xlsx")) else pd.read_csv(payout_file)
    
    return refund_df, payout_df


def load_historical_decisions():
    """Load the latest historical payout and refund decision files."""
    directory = r"C:\xampp\htdocs\fyp\historical"
    
    refund_file = find_latest_file(directory, "historical_refund_decisions")
    payout_file = find_latest_file(directory, "historical_payout_decisions")
    
    refund_df = pd.read_excel(refund_file) if refund_file.endswith(('.xls', '.xlsx')) else pd.read_csv(refund_file)
    payout_df = pd.read_excel(payout_file) if payout_file.endswith(('.xls', '.xlsx')) else pd.read_csv(payout_file)
    
    return refund_df, payout_df, refund_file, payout_file



def merge_rows(group, report_type):
    """Merge rows based on the report type while preserving all row numbers."""
    
    # ✅ Collect all row numbers before merging
    all_row_numbers = list(group["row"]) if "row" in group.columns else []

    # ✅ Capture original column order from the first row
    column_order = group.columns.tolist()

    # ✅ Preserve additional fields (do not merge, take from first row)
    additional_fields = {col: group[col].iloc[0] for col in group.columns if col not in 
                         ["order_id", "transaction_date", "gross_sales_amount", 
                          "refunds_issued", "net_payout_amount", "merge_decision", 
                          "row", "refund_amount", "return_status"]}

    if report_type == "payout":
        merged_row = {
            "row": all_row_numbers,  # ✅ Keep "row" as a list of merged row numbers
            "merged_rows": all_row_numbers,  # ✅ Store all row numbers
            "order_id": group["order_id"].iloc[0],  # Keep order_id
            "transaction_date": group["transaction_date"].max(),  # Latest transaction
            "gross_sales_amount": group["gross_sales_amount"].max(),  # Max sales
            #"refunds_issued": group["refunds_issued"].sum(),  # Total refunds issued
            "refunds_issued": str(pd.to_numeric(group["refunds_issued"], errors="coerce").sum()),
            "net_payout_amount": group["net_payout_amount"].max(),  # Max payout
            "merge_decision": group["merge_decision"].mode()[0] if "merge_decision" in group else "unknown"  
            # Keep the most frequent merge decision or set to "unknown" if missing
     
        }
    
    elif report_type == "refund":
        status_rank = {"Pending": 0, "Approved": 1, "Completed": 2}

        # Gets all status values in the group: (from historical data)
        #  Drops NaNs and converts to list.
        status_values = group["return_status"].dropna().tolist()
        print("Status Values:", status_values)

        # If status values are numeric, map them back to string
        if all(isinstance(x, (int, np.integer)) for x in status_values):
            reverse_map = {0: "Pending", 1: "Approved", 2: "Completed"}
            status_values = [reverse_map.get(x, "Unknown") for x in status_values]
            
        # Find best status,  preparing the historical data to create a training dataset. Assign the "final status" of merged rows
        if status_values:
            best_status = max(status_values, key=lambda x: status_rank.get(x, -1))
        else:
            best_status = "Unknown"

        print("best Status :", best_status)

        merged_row = {
            "row": all_row_numbers,  # ✅ Keep "row" as a list of merged row numbers
            "merged_rows": all_row_numbers,  # ✅ Store all row numbers
            "order_id": group["order_id"].iloc[0],  # Keep order_id
            "refund_amount": group["refund_amount"].sum(),  # Total refund amount
            "return_status": best_status,
            "merge_decision": group["merge_decision"].mode()[0] if "merge_decision" in group else "unknown"  
            # Keep the most frequent merge decision or set to "unknown" if missing
     
        }
    
    else:
        return group.iloc[0]  # If report type is unknown, return the first row
    
    # ✅ Include the preserved additional fields without merging them
    merged_row.update(additional_fields)

    # ✅ Ensure the column order remains **exactly the same** as the original input
    merged_row = {col: merged_row.get(col, None) for col in column_order}

    return pd.Series(merged_row)



def get_next_versioned_filename(filename):
    """Generate a new filename with an incremental version number."""
    base, ext = os.path.splitext(filename)
    match = re.search(r"_(\d+)$", base)
    
    if match:
        version = int(match.group(1)) + 1
        new_base = re.sub(r"_(\d+)$", f"_{version}", base)
    else:
        new_base = f"{base}_1"
    
    return f"{new_base}{ext}"



def train_and_update_models():
    """Train models using historical decision files and update them with new data."""
    refund_df, payout_df, refund_file, payout_file = load_historical_decisions()

    # 🔹 Merge Refund Data by Order ID
    merged_refund_df = refund_df.groupby("order_id").apply(merge_rows, report_type="refund").reset_index(drop=True)

    merged_refund_df.columns = merged_refund_df.columns.str.strip()

    # 🔹 Merge Payout Data by Order ID
    merged_payout_df = payout_df.groupby("order_id").apply(merge_rows, report_type="payout").reset_index(drop=True)

    # 🔹 Define Merge Decision Mapping
    merge_mapping = {0: "keep_latest", 1: "keep_max", 2: "keep_most_frequent"}

    # 🔹 Prepare Refund Data
    if "merge_decision" in merged_refund_df.columns:
        merged_refund_df.dropna(subset=["merge_decision"], inplace=True)

    if "merge_decision" in merged_refund_df.columns:
        merged_refund_df["merge_decision"] = merged_refund_df["merge_decision"].apply(lambda x: merge_mapping.get(x, 0))  # Default to 0 if missing
        print("merge_decision exists!")
    else:
        print("Warning: merge_decision column is missing in merged_refund_df!")

    #merged_refund_df["return_status"] = merged_refund_df["return_status"].astype("category").cat.codes
    status_mapping = {"Pending": 0, "Approved": 1, "Completed": 2}
    merged_refund_df["return_status"] = merged_refund_df["return_status"].map(status_mapping).fillna(-1).astype(int)

    refund_X = merged_refund_df[["return_status", "refund_amount"]]
    refund_y = merged_refund_df["merge_decision"]

    # 🔹 Train Refund Model
    refund_model = RandomForestClassifier()
    refund_model.fit(refund_X, refund_y)

    # 🔹 Prepare Payout Data
    merged_payout_df.dropna(subset=["merge_decision"], inplace=True)
    merged_payout_df["merge_decision"] = merged_payout_df["merge_decision"].astype(int)  # Ensure numeric values remain
    payout_X = merged_payout_df[["gross_sales_amount", "refunds_issued", "net_payout_amount"]]
    payout_y = merged_payout_df["merge_decision"]

    # 🔹 Train Payout Model
    payout_model = RandomForestClassifier()
    payout_model.fit(payout_X, payout_y)

    # 🔹 Generate new filenames with incremental versions
    refund_file_new = get_next_versioned_filename(refund_file)
    payout_file_new = get_next_versioned_filename(payout_file)

    # 🔹 Save as new files instead of overwriting
    if refund_file.endswith((".xls", ".xlsx")):
        merged_refund_df.to_excel(refund_file_new, index=False)
    else:
        merged_refund_df.to_csv(refund_file_new, index=False)

    if payout_file.endswith((".xls", ".xlsx")):
        merged_payout_df.to_excel(payout_file_new, index=False)
    else:
        merged_payout_df.to_csv(payout_file_new, index=False)

    print(f"Updated refund file saved as: {refund_file_new}")
    print(f"Updated payout file saved as: {payout_file_new}")

    return {
        "refund_model": refund_model,
        "refund_mapping": {0: "keep_latest", 1: "keep_max", 2: "keep_most_frequent"},
        "payout_model": payout_model,
        "payout_mapping": {0: "keep_latest", 1: "keep_max", 2: "keep_most_frequent"},
        "status_mapping": status_mapping
    }




_model_cache = None

def get_ml_models():
    global _model_cache
    if _model_cache is None:
        print("Training models...")
        _model_cache = train_and_update_models()
        print("Model Loaded ✅")
    return _model_cache
