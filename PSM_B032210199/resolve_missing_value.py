from fastapi import FastAPI
from pydantic import BaseModel
import pandas as pd
from sklearn.ensemble import RandomForestRegressor, RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.impute import KNNImputer
import numpy as np
from sklearn.preprocessing import LabelEncoder
from sklearn.linear_model import LinearRegression
from typing import Dict, List, Any

app = FastAPI()

class MissingValueDetail(BaseModel):
    report_type: str
    row: int
    column: int
    column_name: str

class MVRequest(BaseModel):
    missing_values: List[MissingValueDetail]
    data: List[Dict[str, Any]]  # Add this field to accept full dataset





def train_rf_refunds_issued(df, target_column, input_features):
    """Train and return the dataset for dynamic feature modeling."""
    df_train = df.dropna(subset=[target_column])
    if df_train.empty:
        return None, None
    return df_train, target_column


def resolve_payout_values(df, missing_columns):
    """Resolve missing values for Payout report"""

    # Random Forest Regression ok
    if 'order_id' in df.columns:
        # Create a mask for missing values in 'order_id'
        missing_mask = df['order_id'].isna()

        # Convert 'transaction_date' to datetime and numeric
        df['transaction_date'] = pd.to_datetime(df['transaction_date'], errors='coerce')
        df['transaction_date_numeric'] = df['transaction_date'].apply(lambda x: x.timestamp() if pd.notna(x) else np.nan)

        # Define all possible features
        all_features = [
            'transaction_date_numeric', 'gross_sales_amount', 'platform_fees',
            'transaction_fees', 'shipping_fees', 'refunds_issued', 'net_payout_amount'
        ]

        # Ensure numeric types
        df[all_features] = df[all_features].apply(pd.to_numeric, errors='coerce')

        # Rows with known order_id (training data)
        train_data = df[~missing_mask].copy()
        train_data['order_id'] = pd.to_numeric(train_data['order_id'], errors='coerce')

        # Predict each missing row independently
        for idx in df[missing_mask].index:
            row = df.loc[idx]
            available_features = [f for f in all_features if pd.notna(row[f])]

            if not available_features:
                continue  # No features to use

            # Build training set using only the same available features
            valid_train = train_data.dropna(subset=available_features + ['order_id'])

            if valid_train.empty:
                continue  # Not enough training data

            X_train = valid_train[available_features]
            y_train = valid_train['order_id']

            model = RandomForestRegressor(n_estimators=100, random_state=42)
            model.fit(X_train, y_train)

            # Prepare input row
            X_row = pd.DataFrame([row[available_features]])

            pred = model.predict(X_row)[0]
            df.at[idx, 'order_id'] = pred

        # Convert predicted order_id to string format
        df['order_id'] = df['order_id'].apply(lambda x: str(int(x)) if pd.notna(x) else x)

        # Clean up
        df.drop(columns=['transaction_date_numeric'], inplace=True)

        resolved_rows = df[df['order_id'].isna()]
        print("Resolved 'order_id' values:")
        print(resolved_rows)



    
    # Transaction Date: Predict using linear Regression and Linear Interpolation:  ok
    if 'transaction_date' in missing_columns:
        df['transaction_date'] = pd.to_datetime(df['transaction_date'], errors='coerce').dt.normalize()
        df['original_index'] = df.index
        df.sort_values(by='original_index', inplace=True)

        df['transaction_date_numeric'] = df['transaction_date'].apply(lambda x: x.timestamp() * 1e9 if pd.notna(x) else np.nan)

        missing_mask = df['transaction_date_numeric'].isna()
        first_valid = df['transaction_date_numeric'].first_valid_index()
        last_valid = df['transaction_date_numeric'].last_valid_index()

        in_between_missing = missing_mask & (df.index > first_valid) & (df.index < last_valid)
        edge_missing = missing_mask & ~in_between_missing

        df.loc[:, 'transaction_date_numeric'] = df['transaction_date_numeric'].interpolate(method='linear', limit_area='inside')

        if edge_missing.any():
            all_features = [
                'order_id', 'gross_sales_amount', 'platform_fees',
                'transaction_fees', 'shipping_fees', 'refunds_issued', 'net_payout_amount'
            ]

            for col in all_features:
                if col not in df.columns:
                    df[col] = np.nan  # preserve nulls

            edge_indexes = df[edge_missing].index

            for idx in edge_indexes:
                row = df.loc[idx]
                available_features = [f for f in all_features if pd.notna(row[f])]
                if not available_features:
                    continue  # can't predict anything

                # Use same features for training with complete data
                train_subset = df[~missing_mask & df[available_features].notna().all(axis=1)]
                if train_subset.empty:
                    continue

                X_train = train_subset[available_features]
                y_train = train_subset['transaction_date_numeric']

                model = LinearRegression()
                model.fit(X_train, y_train)

                X_row = pd.DataFrame([row[available_features]])
                predicted = model.predict(X_row)[0]
                df.at[idx, 'transaction_date_numeric'] = predicted

        df['transaction_date'] = pd.to_datetime(df['transaction_date_numeric'], errors='coerce').dt.normalize()
        df.drop(columns=['transaction_date_numeric'], inplace=True)



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
    
    # Format transaction_date to 'YYYY-MM-DD' safely
    if 'transaction_date' in df.columns:
        df['transaction_date'] = df['transaction_date'].apply(
            lambda x: x.strftime('%Y-%m-%d') if pd.notna(x) else None
        )

    # Ensure NaNs are JSON-compliant
    df = df.where(pd.notnull(df), None)
    
    return df









def impute_product_name(df):
    """Predict missing values for Product Name using Random Forest with dynamic feature selection"""

    if 'product_name' not in df.columns:
        return df

    label_enc = LabelEncoder()

    df_known = df[df['product_name'].notna()].copy()
    df_missing = df[df['product_name'].isna()].copy()

    if df_missing.empty:
        return df

    # Encode product_name for classification
    df_known['product_name_encoded'] = label_enc.fit_transform(df_known['product_name'])

    base_features = ['refund_amount', 'reason_for_return', 'return_status']
    base_features = [f for f in base_features if f in df.columns]

    if 'refund_amount' in df.columns:
        df['refund_amount'] = pd.to_numeric(df['refund_amount'], errors='coerce')

    for idx, row in df_missing.iterrows():
        # Use only features that are present (non-null) for this row
        available_features = [f for f in base_features if pd.notna(row[f])]
        if not available_features:
            continue  # Cannot predict with no features

        # Prepare training data with only rows that have all those features + target
        train_subset = df_known[available_features + ['product_name_encoded']].dropna()
        if train_subset.empty:
            continue  # No training data with those features

        X_train = pd.get_dummies(train_subset[available_features], drop_first=True)
        y_train = train_subset['product_name_encoded']

        # Train model
        model = RandomForestClassifier(n_estimators=100, random_state=42)
        model.fit(X_train, y_train)

        # Prepare test row
        X_row = pd.DataFrame([row[available_features]])
        X_row = pd.get_dummies(X_row, drop_first=True)
        X_row = X_row.reindex(columns=X_train.columns, fill_value=0)

        # Predict
        pred_label = model.predict(X_row)[0]
        df.at[idx, 'product_name'] = label_enc.inverse_transform([pred_label])[0]

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


def train_rf_return_status(df):
    """Train multiple models to predict return_status based on available feature subsets."""

    if 'return_status' not in df.columns:
        return None, None

    df = df.copy()
    df['refund_amount'] = pd.to_numeric(df['refund_amount'], errors='coerce')

    # Remove rows with missing target
    df = df.dropna(subset=['return_status', 'refund_amount'])

    if df.empty:
        return None, None

    label_encoder = LabelEncoder()
    df['return_status_encoded'] = label_encoder.fit_transform(df['return_status'])

    return df, label_encoder





def resolve_refund_values(df, missing_columns):
    """Resolve missing values for Refund report"""

    # Random Forest Regression ok
    if 'order_id' in df.columns:
        # Create a mask for missing values in 'order_id'
        missing_mask = df['order_id'].isna()

        if missing_mask.any():
            # Feature engineering: convert 'return_request_date' to numeric
            df['return_request_date'] = pd.to_datetime(df['return_request_date'], errors='coerce')
            df['return_request_date_numeric'] = df['return_request_date'].astype('int64') / 10**9  # Convert to seconds since epoch

            # Make sure refund_amount is numeric
            df['refund_amount'] = pd.to_numeric(df['refund_amount'], errors='coerce')

            # Base feature pool (some may be missing per row)
            base_features = ['return_request_date_numeric', 'refund_amount']

            # Loop through rows with missing order_id
            for idx, row in df[missing_mask].iterrows():
                # Dynamically select available features for this row
                available_features = [f for f in base_features if pd.notna(row[f])]
                if not available_features:
                    continue  # Skip row if no usable features

                # Filter training data to have same available features (and no missing values)
                train_data = df[~missing_mask & df[available_features].notna().all(axis=1)]

                if train_data.empty:
                    continue  # No training data with the same features

                X_train = train_data[available_features]
                y_train = pd.to_numeric(train_data['order_id'], errors='coerce')

                # Train model
                model = RandomForestRegressor(n_estimators=100, random_state=42)
                model.fit(X_train, y_train)

                # Prepare row for prediction
                X_row = pd.DataFrame([row[available_features]])
                prediction = model.predict(X_row)[0]

                # Impute predicted value
                df.at[idx, 'order_id'] = prediction

            # Convert order_id back to string format
            df['order_id'] = df['order_id'].apply(lambda x: str(int(x)) if pd.notna(x) else x)

            # Drop temporary column
            df.drop(columns=['return_request_date_numeric'], inplace=True)

            # Show any unresolved rows
            unresolved = df[df['order_id'].isna()]
            print("Unresolved 'order_id' values (if any):")
            print(unresolved)

    



    # Product Name: Predict using Random Forest ok
    if 'product_name' in missing_columns:
        df = impute_product_name(df)
    




    # Return Request Date: Predict using linear Regression and Linear Interpolation ok 
    if 'return_request_date' in missing_columns:
        # Step 1: Ensure datetime format, Invalid or missing values become NaT (Not a Time)
        df['return_request_date'] = pd.to_datetime(df['return_request_date'], errors='coerce').dt.normalize()

        # Use index order to keep original sequence
        df['original_row'] = df.index  # Save order
        df.sort_values(by='original_row', inplace=True)  # Interpolation follows this order

        # Step 2: Converts dates to integer timestamps (nanoseconds since epoch) for math operations like interpolation or regression.
        df['return_request_date_numeric'] = df['return_request_date'].apply(lambda x: x.timestamp() * 1e9 if pd.notna(x) else np.nan)

        # Step 4: Identify missing entries
        missing_mask = df['return_request_date_numeric'].isna()
        # Detect boundaries of valid values, Edge missing values (at the start or end, can't interpolate here).
        first_valid = df['return_request_date_numeric'].first_valid_index()
        last_valid = df['return_request_date_numeric'].last_valid_index()
        # Define in-between missing mask (not at the edges)
        in_between_missing = missing_mask & (df.index > first_valid) & (df.index < last_valid)
        edge_missing = missing_mask & ~in_between_missing  # start or end

        # Only fills missing values that are between valid values (not at the edges) using linear interpolation
        df.loc[:, 'return_request_date_numeric'] = df['return_request_date_numeric'].interpolate(
            method='linear', limit_area='inside')

        # Step 5: Use regression for edge missing values (start n end)
        if edge_missing.any():
            # A set of related features is used to train a linear regression model.
            features = ['order_id', 'refund_amount']

            # Ensure all features exist
            for col in features:
                if col not in df:
                    df[col] = 0

            # rows with known dates and complete features.
            train_data = df.loc[~missing_mask & df[features].notna().all(axis=1)]
            # edge rows missing transaction_date but have all feature data.
            test_data = df.loc[edge_missing & df[features].notna().all(axis=1)]

            if not train_data.empty and not test_data.empty:
                X_train = train_data[features]
                y_train = train_data['return_request_date_numeric']
                X_test = test_data[features]

                # Fit a linear regression model and predict the missing date values.
                model = LinearRegression()
                model.fit(X_train, y_train)
                predicted = model.predict(X_test)

                # Ensure predictions are logically positioned
                for i, idx in enumerate(test_data.index):
                    pred_value = predicted[i]

                    if idx < first_valid:  # Missing at the start
                        # Make sure predicted date is BEFORE the first valid one
                        first_known_date = df.loc[first_valid, 'return_request_date_numeric']
                        if pred_value >= first_known_date:
                            # Nudge it earlier logically based on average time difference
                            avg_diff = y_train.diff().dropna().mean()
                            pred_value = first_known_date - abs(avg_diff)

                    elif idx > last_valid:  # Missing at the end
                        last_known_date = df.loc[last_valid, 'return_request_date_numeric']
                        if pred_value <= last_known_date:
                            avg_diff = y_train.diff().dropna().mean()
                            pred_value = last_known_date + abs(avg_diff)

                    # Update the prediction
                df.loc[idx, 'return_request_date_numeric'] = pred_value

                # Fill predicted values
                #df.loc[test_data.index, 'return_request_date_numeric'] = predicted

        # Step 6: Convert back to datetime
        df['return_request_date'] = pd.to_datetime(df['return_request_date_numeric'], errors='coerce').dt.normalize()

        # Cleanup
        df.drop(columns=['return_request_date_numeric'], inplace=True)




    # Refund Amount: Random Forest ok
    if 'refund_amount' in missing_columns:
        # Features needed for refund_amount prediction
        features = ['order_id', 'product_name', 'reason_for_return']
        
        # Train the model with available data
        model = train_rf_refund_amount(df, features)
        if model:
            missing_mask = df['refund_amount'].isna()  # Rows with missing refund_amount
            
            # Only select rows with missing refund_amount
            X_missing = df.loc[missing_mask, features]
            
            # Dynamically drop missing features for prediction
            X_missing = X_missing.dropna(axis=1, how='any')  # Drop columns with missing values

            # If there are still columns remaining to predict, proceed
            if not X_missing.empty:
                # Convert categorical features into dummy variables for prediction
                X_missing = pd.get_dummies(X_missing, drop_first=True)
                
                # Align missing data with training features
                X_missing = X_missing.reindex(columns=model.feature_names_in_, fill_value=0)
                
                # Predict and update the missing values in the dataframe
                df.loc[missing_mask, 'refund_amount'] = model.predict(X_missing)
                print("Predicted missing refund_amount values.")
            else:
                print("No valid features available to predict refund_amount for some rows.")
    
    


    # Reason for Return: Predict using Random Forest Classifier ok 
    if 'reason_for_return' in missing_columns:
        # Encode categorical target labels
        le = LabelEncoder()
        non_null_mask = df['reason_for_return'].notna()
        df.loc[non_null_mask, 'reason_for_return_encoded'] = le.fit_transform(df.loc[non_null_mask, 'reason_for_return'])

        base_features = ['order_id', 'product_name', 'refund_amount', 'return_status']
        known_data = df[df['reason_for_return'].notna()].copy()

        # Train base models for each row dynamically
        missing_rows = df[df['reason_for_return'].isna()]

        for idx, row in missing_rows.iterrows():
            # Dynamically pick only the available (non-null) features for this row
            available_features = [f for f in base_features if pd.notna(row[f])]
            if len(available_features) == 0:
                continue  # Skip if no usable inputs

            # Prepare training data with the same available features
            train_subset = known_data[available_features + ['reason_for_return_encoded']].dropna()

            if train_subset.empty:
                continue  # No usable training data

            X_train = pd.get_dummies(train_subset[available_features], drop_first=True)
            y_train = train_subset['reason_for_return_encoded']

            # Prepare input row
            X_row = pd.DataFrame([row[available_features]])
            X_row = pd.get_dummies(X_row, drop_first=True)
            X_row = X_row.reindex(columns=X_train.columns, fill_value=0)  # Align with training columns

            # Train model and predict
            model = RandomForestClassifier(n_estimators=100, random_state=42)
            model.fit(X_train, y_train.astype(int))

            pred = model.predict(X_row)[0]
            df.at[idx, 'reason_for_return'] = le.inverse_transform([pred])[0]

        # Cleanup encoded column
        df.drop(columns=['reason_for_return_encoded'], inplace=True, errors='ignore')



    



    # Return Status: Predict using Random Forest Classifier ok 
    if 'return_status' in missing_columns:
        training_data, label_encoder = train_rf_return_status(df)

        if training_data is not None:
            features = ['order_id', 'refund_amount', 'reason_for_return', 'product_name']
            missing_rows = df[df['return_status'].isna()]

            for idx, row in missing_rows.iterrows():
                # Identify non-null features for this row
                available_features = [f for f in features if pd.notna(row[f])]

                if not available_features:
                    continue  # Skip if no usable features

                # Prepare training data with matching available features
                train_subset = training_data.dropna(subset=available_features)
                if train_subset.empty:
                    continue

                X_train = train_subset[available_features]
                y_train = train_subset['return_status_encoded']  

                # Encode categorical variables
                X_train = pd.get_dummies(X_train, drop_first=True)

                # Train model on the fly
                model = RandomForestClassifier(n_estimators=100, random_state=42)
                model.fit(X_train, y_train)

                # Prepare input row
                X_row = pd.DataFrame([row[available_features]])
                X_row = pd.get_dummies(X_row, drop_first=True)
                X_row = X_row.reindex(columns=X_train.columns, fill_value=0)

                # Predict and update
                pred = model.predict(X_row)[0]
                df.at[idx, 'return_status'] = label_encoder.inverse_transform([pred])[0]


    # Format return_request_date to 'YYYY-MM-DD' safely
    if 'return_request_date' in df.columns:
        df['return_request_date'] = df['return_request_date'].apply(
            lambda x: x.strftime('%Y-%m-%d') if pd.notna(x) else None
        )

    # Ensure NaNs are JSON-compliant
    df = df.where(pd.notnull(df), None)

    return df





@app.post("/resolve_missing_value")
async def resolve(request: MVRequest):
    print("📥 Incoming Request Data:", request.data)
    print("🔍 Missing Values:", request.missing_values)
    
    if not request.data:
        return {"error": "No data provided"}
    
    # Extract report type
    report_type = request.missing_values[0].report_type if request.missing_values else None

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
    
    # Convert `request.data` into a DataFrame
    try:
        df = pd.DataFrame(
            [entry['data'] for entry in request.data],
            columns=column_names,
            index=[entry['row'] for entry in request.data] # include the original row number from data
        ).reset_index().rename(columns={'index': 'row'})

    except Exception as e:
        return {"error": f"Error processing data: {str(e)}"}

    # Extract missing column names
    #missing_columns = {mv.column_name.lower().replace(" ", "_") for mv in request.missing_values}
    # Mapping of verbose or inconsistent names to actual DataFrame column names
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

    # Normalize and map missing column names
    raw_missing_columns = {mv.column_name.lower().replace(" ", "_") for mv in request.missing_values}
    missing_columns = {column_name_mapping.get(col, col) for col in raw_missing_columns}

    print("📥 mv Data:", missing_columns)
    
    if request.missing_values[0].report_type == "payout":
        #df = resolve_payout_values(df)
        df = resolve_payout_values(df, missing_columns)
    elif request.missing_values[0].report_type == "refund":
        #df = resolve_refund_values(df)
        df = resolve_refund_values(df, missing_columns)
    else:
        return {"error": "Unknown report type"}
    
    resolved_data = df.to_dict(orient="records")
    print("📤 Resolved Data:", resolved_data)
    
    return {"resolved": resolved_data}


 

@app.get("/")
async def home():
    return {"message": "FastAPI Server is Running!"}

if __name__ == '__main__':
    import uvicorn
    print("FastAPI is running on port 8001...")
    uvicorn.run(app, host="127.0.0.1", port=8001, reload=True)
