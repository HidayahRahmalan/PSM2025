from fastapi import FastAPI
from pydantic import BaseModel
import pandas as pd
import numpy as np
from typing import Dict, List, Any
from sklearn.ensemble import RandomForestClassifier,RandomForestRegressor
from sklearn.preprocessing import LabelEncoder
from sklearn.linear_model import LinearRegression
from sklearn.model_selection import train_test_split
from datetime import timedelta

app = FastAPI()

class InaccuracyDetail(BaseModel):
    row: int
    column: int
    column_name: str

class RowData(BaseModel):
    row: int
    data: List[Any]

# IARequest model
class IARequest(BaseModel):
    negative_values: List[InaccuracyDetail] = []
    incorrect_amount_calculation: List[InaccuracyDetail] = []
    misleading_timestamps: List[InaccuracyDetail] = []
    wrong_status_value: List[InaccuracyDetail] = []
    data: List[RowData]
    report_type: str


# Incorrect Amount Calculations in Payout report/
def correct_payout_inaccuracies(df: pd.DataFrame, inaccurate_columns: set) -> pd.DataFrame:
    """Correct inaccuracies in Payout report based on formulas."""

    # Ensure columns are numeric
    numeric_cols = ['gross_sales_amount', 'platform_fees', 'transaction_fees', 'shipping_fees', 'refunds_issued', 'net_payout_amount']
    for col in numeric_cols:
        df[col] = pd.to_numeric(df[col], errors='coerce')

    # Correct each column based on business logic
    if 'platform_fees' in inaccurate_columns:
        df['platform_fees'] = (df['gross_sales_amount'] * 0.05).round(2)

    if 'transaction_fees' in inaccurate_columns:
        df['transaction_fees'] = (df['gross_sales_amount'] * 0.015).round(2)

    if 'shipping_fees' in inaccurate_columns:
        df['shipping_fees'] = df['gross_sales_amount'].apply(
            lambda x: 4 if x <= 150 else (5 if x <= 250 else 6)
        )

    if 'gross_sales_amount' in inaccurate_columns:
        df['gross_sales_amount'] = (
            df['platform_fees'] + df['transaction_fees'] + df['shipping_fees'] +
            df['refunds_issued'] + df['net_payout_amount']
        ).round(2)

    if 'net_payout_amount' in inaccurate_columns:
        df['net_payout_amount'] = (
            df['gross_sales_amount'] - 
            (df['platform_fees'] + df['transaction_fees'] + df['shipping_fees'] + df['refunds_issued'])
        ).round(2)

    return df


# Wrong Status Values in Refund report (RandomForestClassifier)/
def resolve_return_status_with_ml(df: pd.DataFrame, affected_rows: List[int]) -> pd.DataFrame:
    """Train an ML model on-the-fly using input data to predict correct return_status"""

    # Convert refund_amount to numeric
    df['refund_amount'] = pd.to_numeric(df['refund_amount'], errors='coerce').fillna(0)

    # Normalize return status
    df['return_status_clean'] = df['return_status'].astype(str).str.strip().str.lower()

    # Encode reason
    le_reason = LabelEncoder()
    df['reason_for_return_encoded'] = le_reason.fit_transform(df['reason_for_return'].astype(str).fillna("unknown"))

    # Use only valid rows for training
    valid_conditions = (
        ((df['refund_amount'] > 0) & df['return_status_clean'].isin(['approved', 'completed'])) |
        ((df['refund_amount'] == 0) & df['return_status_clean'].isin(['rejected', 'pending']))
    )
    train_df = df[valid_conditions].copy()

    if train_df.empty:
        print("⚠️ Not enough valid rows to train model.")
        return df

    # Encode return status
    le_status = LabelEncoder()
    train_df['return_status_encoded'] = le_status.fit_transform(train_df['return_status_clean'])

    # Train model
    X_train = train_df[['refund_amount', 'reason_for_return_encoded']]
    y_train = train_df['return_status_encoded']
    model = RandomForestClassifier(n_estimators=50, random_state=42)
    model.fit(X_train, y_train)

    # Predict for affected rows
    predict_df = df[df['row'].isin(affected_rows)].copy()
    X_pred = predict_df[['refund_amount', 'reason_for_return_encoded']]
    predicted_encoded = model.predict(X_pred)
    predicted_statuses = le_status.inverse_transform(predicted_encoded)
    predicted_statuses_cap = [status.capitalize() for status in predicted_statuses]

    # 🔒 Enforce domain logic consistency
    for i, idx in enumerate(predict_df.index):
        refund_amt = predict_df.loc[idx, 'refund_amount']
        predicted_status = predicted_statuses_cap[i]

        if refund_amt == 0 and predicted_status not in ['Rejected', 'Pending']:
            predicted_statuses_cap[i] = 'Rejected'  # Default fallback

        elif refund_amt > 0 and predicted_status not in ['Approved', 'Completed']:
            predicted_statuses_cap[i] = 'Approved'  # Default fallback

    # Update the main dataframe
    df.loc[df['row'].isin(affected_rows), 'return_status'] = predicted_statuses_cap

    return df


# resolve negative value
def train_rf_refunds_issued(df, target_column, input_features):
    """Train and return the dataset for dynamic feature modeling."""
    df_train = df.dropna(subset=[target_column])
    if df_train.empty:
        return None, None
    return df_train, target_column

def resolve_payout_values(df, missing_columns):
    # Gross Sales Amount: Sum calculation, linear regression ok
    if 'gross_sales_amount' in missing_columns:
        # Define the columns used in the formula
        supporting_cols = ['platform_fees', 'transaction_fees', 'shipping_fees', 'refunds_issued', 'net_payout_amount']
        
        # Convert relevant columns to numeric
        for col in supporting_cols:
            df[col] = pd.to_numeric(df[col], errors='coerce')

        # Split into complete and incomplete rows based on supporting columns
        complete_mask = df[supporting_cols].notna().all(axis=1)
        complete_rows = df[complete_mask]
        incomplete_rows = df[~complete_mask]

        # Direct formula calculation for complete rows
        df.loc[complete_mask, 'gross_sales_amount'] = (
            df.loc[complete_mask, 'platform_fees'] +
            df.loc[complete_mask, 'transaction_fees'] +
            df.loc[complete_mask, 'shipping_fees'] +
            df.loc[complete_mask, 'net_payout_amount'] -
            df.loc[complete_mask, 'refunds_issued']
        )

        # Select features for regression (exclude gross_sales_amount and supporting columns)
        candidate_features = df.columns.difference(['gross_sales_amount'] + supporting_cols)
        candidate_features = [col for col in candidate_features if pd.api.types.is_numeric_dtype(df[col])]

        # Filter out rows with missing candidate features
        model_df = df.loc[complete_mask, candidate_features + ['gross_sales_amount']].dropna()
        
        if not model_df.empty and candidate_features:
            X_train = model_df[candidate_features]
            y_train = model_df['gross_sales_amount']

            # Train model
            model = LinearRegression()
            model.fit(X_train, y_train)

            # Apply model to rows where gross_sales_amount is missing and at least one supporting col is missing
            predict_mask = df['gross_sales_amount'].isna() & (~complete_mask)

            # Ensure only rows with no missing values in selected features
            X_pred = df.loc[predict_mask, candidate_features].dropna()

            # Predict and assign
            if not X_pred.empty:
                y_pred = model.predict(X_pred)
                df.loc[X_pred.index, 'gross_sales_amount'] = y_pred

        # Round the final results to 2 decimal places
        df['gross_sales_amount'] = df['gross_sales_amount'].round(2)




    # Platform Fees (~5% of Gross Sales) ok
    if 'platform_fees' in missing_columns:
        # Ensure 'gross_sales_amount' is fully numeric
        df['gross_sales_amount'] = pd.to_numeric(df['gross_sales_amount'], errors='coerce')

        # Warn if any NaN values in 'gross_sales_amount' exist
        if df['gross_sales_amount'].isnull().any():
            print("Warning: Some 'gross_sales_amount' values are NaN. Platform fees cannot be calculated.")

        # Only calculate platform fees for rows where it's missing
        df.loc[df['platform_fees'].isna(), 'platform_fees'] = (
            df.loc[df['platform_fees'].isna(), 'gross_sales_amount'] * 0.05
        ).round(2)


    # Transaction Fees (1.5% of gross_sales_amount) ok
    if 'transaction_fees' in missing_columns:
        # Ensure 'gross_sales_amount' is fully numeric
        df['gross_sales_amount'] = pd.to_numeric(df['gross_sales_amount'], errors='coerce')

        # Ensure no NaN values are in 'gross_sales_amount' before calculation
        if df['gross_sales_amount'].isnull().any():
            print("Warning: Some 'gross_sales_amount' values are NaN. Platform fees cannot be calculated.")

        # Apply the calculation only to rows where 'transaction_fees' is NaN
        df.loc[df['transaction_fees'].isnull(), 'transaction_fees'] = df.loc[df['transaction_fees'].isnull(), 'gross_sales_amount'] * 0.015


        # Round the final 'transaction_fees' to 2 decimal places
        df['transaction_fees'] = df['transaction_fees'].round(2)

    
  
    # Shipping Fees Calculation (4, if Gross Sales ≤ 150, 5 if 151 ≤ Gross Sales ≤ 250, 6 if Gross Sales > 250) ok
    if 'shipping_fees' in missing_columns:
        df['gross_sales_amount'] = pd.to_numeric(df['gross_sales_amount'], errors='coerce')
        if df['gross_sales_amount'].isnull().any():
            print("Warning: Some 'gross_sales_amount' values are NaN. shipping fees cannot be calculated.")

        df['shipping_fees'] = df['gross_sales_amount'].apply(lambda x: 4 if x <= 150 else (5 if x <= 250 else 6))
    


    # Refunds Issued: Predict using Random Forest ok
    if 'refunds_issued' in missing_columns:
        features = ['order_id', 'gross_sales_amount', 'platform_fees', 'transaction_fees', 'shipping_fees', 'net_payout_amount']
        df_train, target_column = train_rf_refunds_issued(df, 'refunds_issued', features)

        if df_train is None:
            return df  # Not enough data to train

        missing_rows = df[df['refunds_issued'].isna()]

        for idx, row in missing_rows.iterrows():
            # 1. Identify available features for this row
            available_features = [f for f in features if pd.notna(row[f])]
            if not available_features:
                continue  # Can't predict with no features

            # 2. Filter training data for rows that have all these features
            train_subset = df_train[available_features + [target_column]].dropna()
            if train_subset.empty:
                continue  # Not enough matching training data

            X_train = train_subset[available_features]
            y_train = train_subset[target_column]

            # 3. Train a quick model using only available features
            model = RandomForestRegressor(n_estimators=100, random_state=42)
            model.fit(X_train, y_train)

            # 4. Prepare current row for prediction
            X_row = pd.DataFrame([row[available_features]])

            # 5. Predict and update the DataFrame
            pred = model.predict(X_row)[0]
            df.at[idx, 'refunds_issued'] = pred



    # Net Payout Amount Calculation ok
    if 'net_payout_amount' in missing_columns:
        numeric_cols = ['gross_sales_amount', 'platform_fees', 'transaction_fees', 'shipping_fees', 'refunds_issued']
        for col in numeric_cols:
            df[col] = pd.to_numeric(df[col], errors='coerce')  # Convert strings to numbers, invalid entries become NaN

        df['net_payout_amount'] = df['gross_sales_amount'] - (df['platform_fees'] + df['transaction_fees'] + df['shipping_fees'] + df['refunds_issued'])
    
    return df

def train_rf_refund_amount(df, features):
    """Train a model to predict refund_amount without using return_status."""
    if 'refund_amount' not in df.columns:
        return None  # No refund_amount column found
    
    # Drop rows with missing target values (refund_amount)
    df_train = df.dropna(subset=['refund_amount'])

    if df_train.empty or df_train.shape[0] < 3:
        return None  # Not enough data to train
    
    X = df_train[features]
    y = df_train['refund_amount']
    
    # Convert categorical features into dummy variables
    X = pd.get_dummies(X, drop_first=True)
    
    # Choose model based on the size of the dataset
    if df_train.shape[0] < 10:
        model = LinearRegression()
    else:
        model = RandomForestRegressor(n_estimators=100, random_state=42)
    
    # Split into train and test datasets
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    model.fit(X_train, y_train)
    
    return model

def resolve_refund_values(df, missing_columns):
    # Step 0: Conditional handling based on return_status for negative (now NaN) values
    if 'refund_amount' in missing_columns:
        if 'return_status' in df.columns:
            status_col = df['return_status'].str.lower().str.strip()
            condition_zero = df['refund_amount'].isna() & status_col.isin(['pending', 'rejected'])
            df.loc[condition_zero, 'refund_amount'] = 0
            print(f"Set refund_amount = 0 for {condition_zero.sum()} rows with status Pending/Rejected")
            remaining_missing_mask = df['refund_amount'].isna() & ~status_col.isin(['pending', 'rejected'])
        else:
            remaining_missing_mask = df['refund_amount'].isna()

        # Step 1: Train the model
        features = ['order_id', 'product_name', 'reason_for_return']
        model = train_rf_refund_amount(df, features)

        # Step 2: Define price hints for known product types
        price_hint_map = {
            "earbuds": 15,
            "smartwatch": 50,
            "speaker": 25,
            "bag": 20,
            "headphones": 30,
            "headset": 30,
            "keyboard": 20,
            "mouse": 15,
            "tracker": 40,
            "usb c hub": 25,
            "charger": 20
        }

        def extract_price_hint(product_name):
            name = product_name.lower()
            for key, hint in price_hint_map.items():
                if key in name:
                    return hint
            return 0  # Default if no match

        # Step 3: Predict refund_amount with fallback to price hint
        if model and remaining_missing_mask.any():
            X_missing = df.loc[remaining_missing_mask, features].copy()
            X_missing = X_missing.dropna(axis=1, how='any')

            if not X_missing.empty:
                X_encoded = pd.get_dummies(X_missing, drop_first=True)
                X_encoded = X_encoded.reindex(columns=model.feature_names_in_, fill_value=0)
                predictions = model.predict(X_encoded)

                # Assign predicted or price_hint values
                for idx, row in X_missing.iterrows():
                    predicted_value = predictions[list(X_missing.index).index(idx)]
                    product = row['product_name']
                    hint = extract_price_hint(product)
                    final_value = max(predicted_value, hint)
                    df.at[idx, 'refund_amount'] = final_value

                print("Predicted and adjusted refund_amount values based on price hints.")
            else:
                print("No valid features available to predict refund_amount for some rows.")

    return df

def resolve_negative_values(df: pd.DataFrame, report_type: str, inaccuracies: List[InaccuracyDetail]) -> pd.DataFrame:
    """Replace negative values with NaN and re-resolve them using model-based imputation"""
    
    # Normalize column names
    col_mapping = {
        'platform_fees_(commissions_or_service_charges)': 'platform_fees',
        'platform_fees': 'platform_fees',
        'gross_sales_amount': 'gross_sales_amount',
        'transaction_fees': 'transaction_fees',
        'shipping_fees': 'shipping_fees',
        'refunds_issued': 'refunds_issued',
        'net_payout_amount': 'net_payout_amount',
        'refund_amount': 'refund_amount'
    }

    # Step 1: Replace negative values with NaN
    for inacc in inaccuracies:
        col = col_mapping.get(inacc.column_name.lower().replace(" ", "_"), inacc.column_name)
        if col in df.columns:
            try:
                df.loc[df['row'] == inacc.row, col] = np.nan
            except Exception as e:
                print(f"Error nullifying negative value at row {inacc.row}, col {col}: {e}")

    # Step 2: Re-run the appropriate resolver
    missing_columns = df.columns[df.isna().any()].tolist()

    if report_type == "payout":
        df = resolve_payout_values(df, missing_columns)
    elif report_type == "refund":
        df = resolve_refund_values(df, missing_columns)
    
    return df




# format n typo
def resolve_invalid_formats_strict(df: pd.DataFrame, date_col: str) -> pd.Series:
    import re
    date_series = df[date_col].astype(str)
    resolved_dates = pd.Series([pd.NaT] * len(df), index=df.index)

    # Strict format handlers
    format_patterns = [
        (r"^\d{4}\.\d{2}\.\d{2}$", "%Y.%m.%d"),
        (r"^\d{2}/\d{2}/\d{4}$", "%m/%d/%Y"),
        (r"^\d{2}-\d{2}-\d{4}$", "%d-%m-%Y"),
        (r"^\d{4}/\d{2}/\d{2}$", "%Y/%m/%d"),
        (r"^\d{4}-\d{2}-\d{2}$", "%Y-%m-%d"),  # default
    ]

    for pattern, fmt in format_patterns:
        mask = date_series.str.match(pattern)
        parsed = pd.to_datetime(date_series[mask], format=fmt, errors="coerce")
        resolved_dates.update(parsed)

    # Day-first fallback only for remaining unmatched rows
    unresolved_mask = resolved_dates.isna()
    if unresolved_mask.any():
        fallback_parsed = pd.to_datetime(date_series[unresolved_mask], dayfirst=True, errors='coerce')
        resolved_dates.update(fallback_parsed)

    return resolved_dates.dt.normalize()

# Logical Consistency
# Detect and nullify logical inconsistencies
def nullify_logical_inconsistencies(df: pd.DataFrame, date_col: str) -> pd.DataFrame:
    numeric_col = f'{date_col}_numeric'
    df[numeric_col] = df[date_col].apply(lambda x: x.timestamp() * 1e9 if pd.notna(x) else np.nan)
    
    for i in range(1, len(df)):
        if df.loc[i, numeric_col] < df.loc[i - 1, numeric_col]:
            df.loc[i, numeric_col] = np.nan

    return df

# **New**: Chronological Consistency Check - Ensuring no date is before its predecessor
def nullify_chronological_violations(df: pd.DataFrame, date_col: str) -> pd.Series:
    df = df.copy()
    date_col_numeric = f"{date_col}_numeric"
    df[date_col_numeric] = df[date_col].apply(lambda x: x.timestamp() * 1e9 if pd.notna(x) else np.nan)

    for i in range(1, len(df)):
        if df.loc[i, date_col_numeric] < df.loc[i - 1, date_col_numeric]:
            df.loc[i, date_col_numeric] = np.nan

    return df

# Detect and nullify future dates
def nullify_future_dates(df: pd.DataFrame, date_col: str) -> pd.DataFrame:
    today = pd.Timestamp.now().normalize()
    df[date_col] = df[date_col].apply(lambda x: x if pd.isna(x) or x <= today else pd.NaT)
    return df


def interpolate_and_predict_dates(df: pd.DataFrame, date_col: str, report_type: str) -> pd.DataFrame:
    numeric_col = f'{date_col}_numeric'

    missing_mask = df[numeric_col].isna()
    first_valid = df[numeric_col].first_valid_index()
    last_valid = df[numeric_col].last_valid_index()

    in_between_missing = missing_mask & (df.index > first_valid) & (df.index < last_valid)
    edge_missing = missing_mask & ~in_between_missing

    # Interpolate inside valid range
    df[numeric_col] = df[numeric_col].interpolate(method='linear', limit_area='inside')

    # Predict at edges using regression
    if edge_missing.any():
        features = ['order_id']
        if report_type == 'refund':
            features.append('refund_amount')
        elif report_type == 'payout':
            features.append('gross_sales_amount')

        for col in features:
            if col not in df:
                df[col] = 0

        train_data = df.loc[~missing_mask & df[features].notna().all(axis=1)]
        test_data = df.loc[edge_missing & df[features].notna().all(axis=1)]

        if not train_data.empty and not test_data.empty():
            X_train = train_data[features]
            y_train = train_data[numeric_col]
            X_test = test_data[features]

            model = LinearRegression()
            model.fit(X_train, y_train)
            predicted = model.predict(X_test)

            avg_diff = y_train.diff().dropna().mean()

            # **New**: Ensure predicted dates are constrained within known chronology bounds
            for i, idx in enumerate(test_data.index):
                pred_value = predicted[i]
                if idx < first_valid:
                    pred_value = min(pred_value, df.loc[first_valid, numeric_col] - abs(avg_diff))
                elif idx > last_valid:
                    pred_value = max(pred_value, df.loc[last_valid, numeric_col] + abs(avg_diff))
                df.loc[idx, numeric_col] = pred_value

    # Convert back to datetime
    df[date_col] = pd.to_datetime(df[numeric_col], errors='coerce').dt.normalize()
    df.drop(columns=[numeric_col], inplace=True)
    return df

# weekend adjustment
import pandas as pd

def apply_weekend_adjustment(df: pd.DataFrame, date_col: str) -> pd.DataFrame:
    df = df.copy()
    df = df.sort_values(by=date_col).reset_index(drop=True)

    adjusted_dates = []

    # Start from the first date
    current_date = df.loc[0, date_col]

    # Shift if it's weekend
    if current_date.weekday() >= 5:
        current_date += pd.Timedelta(days=(7 - current_date.weekday()))

    # Assign weekday-only dates going forward
    for _ in range(len(df)):
        # Skip weekends
        while current_date.weekday() >= 5:
            current_date += pd.Timedelta(days=1)

        adjusted_dates.append(current_date)
        current_date += pd.Timedelta(days=1)

    df[date_col] = adjusted_dates
    return df

# **New**: Final Validation - Check and remove any future dates
def validate_final_dates(df: pd.DataFrame, date_col: str) -> pd.DataFrame:
    now = pd.Timestamp.now().normalize()
    df[date_col] = df[date_col].apply(lambda x: x if pd.notna(x) and x <= now else pd.NaT)
    return df

# Misleading Timestamps in Refund and Payout reports 
def resolve_misleading_timestamps(df: pd.DataFrame, report_type: str, affected_rows: List[int]) -> pd.DataFrame:
    date_col = 'transaction_date' if report_type == 'payout' else 'return_request_date'

    df_copy = df.copy()

    # Step 1: Strict format handling
    df_copy[date_col] = resolve_invalid_formats_strict(df_copy, date_col)

    # 🔧 Step 2: Nullify future dates *before* numeric conversion
    df_copy = nullify_future_dates(df_copy, date_col)

    # Step 3: Nullify logical inconsistencies (uses timestamp conversion)
    df_copy = nullify_logical_inconsistencies(df_copy, date_col)

    # Step 4: Nullify chronological violations
    df_copy = nullify_chronological_violations(df_copy, date_col)

    # Step 5: Interpolate & predict
    df_copy = interpolate_and_predict_dates(df_copy, date_col, report_type)

    # Step 6: Weekend adjustment
    if report_type == 'payout':
        df_copy = apply_weekend_adjustment(df_copy, date_col)
    
    # Save the fully adjusted dates for weekend handling
    weekend_adjusted_dates = df_copy[date_col].copy()

    # Step 7: Final validation (strip any accidental future values)
    df_copy = validate_final_dates(df_copy, date_col)

    # Format for output, keeping only date (no time)
    df_copy[date_col] = df_copy[date_col].dt.date  # Remove time part

    # Update only affected rows with original fixes (not weekend adjustments)
    df_final = df.copy()
    df_final.loc[df_final['row'].isin(affected_rows), date_col] = df_copy.loc[df_copy['row'].isin(affected_rows), date_col]

    # Step B: Apply weekend-adjusted dates to the entire DataFrame to maintain weekday order
    if report_type == 'payout':
        df_final[date_col] = weekend_adjusted_dates.dt.date  # Remove time part

    return df_final



@app.post("/resolve_inaccuracies")
async def resolve_inaccuracies(request: IARequest):
    print("📥 Incoming Request Data:", request.data)

    if not request.data:
        return {"error": "No data provided"}
    
    # Extract report type
    report_type = request.report_type

    # Define expected column names based on report type
    column_names = []
    if report_type == "payout":
        column_names = ['order_id', 'transaction_date', 'gross_sales_amount', 'platform_fees', 
                        'transaction_fees', 'shipping_fees', 'refunds_issued', 'net_payout_amount']
    elif report_type == "refund":
        column_names = ['order_id', 'product_name', 'return_request_date', 'refund_amount', 
                        'reason_for_return', 'return_status']
    else:
        return {"error": "Unknown report type"}

    # Convert incoming data into DataFrame
    try:
        df = pd.DataFrame(
            [entry.data for entry in request.data],
            columns=column_names,
            index=[entry.row for entry in request.data]
        ).reset_index().rename(columns={'index': 'row'})
    except Exception as e:
        return {"error": f"Error processing data: {str(e)}"}

    # 1️⃣ Resolve misleading timestamps (only if category present)
    if request.misleading_timestamps and report_type in ["refund", "payout"]:
        affected_rows = [ia.row for ia in request.misleading_timestamps]
        df = resolve_misleading_timestamps(df, report_type, affected_rows)

    # 2️⃣ Resolve negative values
    if request.negative_values and report_type in ["refund", "payout"]:
        df = resolve_negative_values(df, report_type, request.negative_values)

    # 3️⃣ Resolve incorrect payout calculation (only if payout and category present)
    if request.incorrect_amount_calculation and report_type == "payout":
        # Normalize column names
        column_name_mapping = {
            'order_id': 'order_id',
            'transaction_date': 'transaction_date',
            'gross_sales_amount': 'gross_sales_amount',
            'platform_fees_(commissions_or_service_charges)': 'platform_fees',
            'platform_fees': 'platform_fees',
            'transaction_fees': 'transaction_fees',
            'shipping_fees': 'shipping_fees',
            'refunds_issued': 'refunds_issued',
            'net_payout_amount': 'net_payout_amount',
        }

        raw_inaccurate_columns = {
            ia.column_name.lower().replace(" ", "_") for ia in request.incorrect_amount_calculation
        }
        inaccurate_columns = {column_name_mapping.get(col, col) for col in raw_inaccurate_columns}

        print("📥 Inaccurate Columns to Correct:", inaccurate_columns)
        df = correct_payout_inaccuracies(df, inaccurate_columns)

    # 4️⃣ Resolve wrong return status (only if refund and category present)
    if request.wrong_status_value and report_type == "refund":
        affected_rows = [ia.row for ia in request.wrong_status_value]
        if affected_rows:
            df = resolve_return_status_with_ml(df, affected_rows)
        df.drop(columns=["return_status_clean", "reason_for_return_encoded", "original_row"], errors="ignore", inplace=True)

    # Final response
    #resolved_data = df.to_dict(orient="records")
    
    # Hide internal-use-only columns from the response
    columns_to_hide = ['original_row']
    response_df = df.drop(columns=columns_to_hide, errors="ignore")
    resolved_data = response_df.to_dict(orient="records")

    print("📤 Resolved Data:", resolved_data)
    return {"resolved": resolved_data}


@app.get("/")
async def home():
    return {"message": "FastAPI Server is Running for Inaccuracy Resolution!"}

if __name__ == "__main__":
    import uvicorn
    print("FastAPI is running on port 8002...")
    uvicorn.run(app, host="127.0.0.1", port=8002, reload=True)
