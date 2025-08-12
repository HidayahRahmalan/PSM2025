import sys
import pandas as pd
import json
import numpy as np
from decimal import Decimal, ROUND_HALF_UP
import re
from datetime import datetime



def round_half_up(n, decimals=0):
    n = Decimal(str(n))
    rounding_quant = Decimal(f'1.{"0" * decimals}')
    return float(n.quantize(rounding_quant, rounding=ROUND_HALF_UP))


def replace_nan_with_none(obj):
    """Recursively replace NaN values with None and Timestamps with ISO strings."""
    if isinstance(obj, dict):
        return {k: replace_nan_with_none(v) for k, v in obj.items()}
    elif isinstance(obj, list):
        return [replace_nan_with_none(v) for v in obj]
    elif isinstance(obj, float) and np.isnan(obj):
        return None
    elif isinstance(obj, pd.Timestamp):
        return obj.isoformat()  # Convert Timestamps to ISO string
    return obj


def is_close(a, b, tol=1e-2):
    return abs(a - b) <= tol

def is_valid_date(date_text):
    """Check if date is in YYYY-MM-DD format."""
    return re.fullmatch(r'\d{4}-\d{2}-\d{2}', str(date_text)) is not None

def is_weekend(date_text):
    """Check if the date is a weekend."""
    date_obj = datetime.strptime(date_text, '%Y-%m-%d')
    return date_obj.weekday() >= 5  # 5 = Saturday, 6 = Sunday


def load_file(file_path):
    """ Load CSV, XLS, or XLSX file into a Pandas DataFrame """
    if file_path.endswith('.csv'):
        #df = pd.read_csv(file_path)
        #df = pd.read_csv(file_path, keep_default_na=True, na_values=["", "NA", "NaN"])
        df = pd.read_csv(file_path, keep_default_na=True, na_values=["", "NA", "NaN"], dtype=str)
    
    elif file_path.endswith('.xlsx'):
        #df = pd.read_excel(file_path, engine='openpyxl')  # Use openpyxl for .xlsx
        #df = pd.read_excel(file_path, engine='openpyxl', keep_default_na=True, na_values=["", "NA", "NaN"])
        #df = pd.read_excel(file_path, engine='openpyxl', dtype={'Order ID': str}, keep_default_na=True, na_values=["", "NA", "NaN"])
        df = pd.read_excel(file_path, engine='openpyxl', dtype=str, keep_default_na=True, na_values=["", "NA", "NaN"])

    elif file_path.endswith('.xls'):
        #df = pd.read_excel(file_path, engine='xlrd')  # Use xlrd for .xls
        #df = pd.read_excel(file_path, engine='xlrd', keep_default_na=True, na_values=["", "NA", "NaN"])
        df = pd.read_excel(file_path, engine='xlrd', dtype=str, keep_default_na=True, na_values=["", "NA", "NaN"])
    
    else:
        raise ValueError("Unsupported file format. Only CSV, XLS, and XLSX are allowed.")

    #print("DEBUG: Data Loaded from file:", df.to_dict(orient="records"))  # Debugging output

    # Convert empty strings to NaN for proper missing value detection
    #df.replace(r'^\s*$', None, regex=True, inplace=True)
    df.replace(r'^\s*$', np.nan, regex=True, inplace=True)

    if 'Order ID' in df.columns:
        df['Order ID'] = df['Order ID'].apply(lambda x: str(int(x)) if pd.notnull(x) and isinstance(x, float) else str(x) if pd.notnull(x) else None)



  
    return df  # No row numbers added


def detect_duplicates(df):
    """Detect and classify duplicates without relying on row numbers."""

    if "Order ID" not in df.columns:
        return {"error": "No 'Order ID' column found."}

    # Trim spaces in column names
    df.columns = df.columns.str.strip()
    
    # Trim spaces in all values (for object type columns)
    for col in df.select_dtypes(include=['object']).columns:
        df[col] = df[col].str.strip()
        
    duplicates = {
        "fully_identical_rows": [],
        "same_order_id_diff_attributes": [],
        "same_attributes_diff_order_id": []
    }

    attributes_without_order_id = [col for col in df.columns if col != "Order ID"]

    # 1️⃣ **Detect Fully Identical Rows (All attributes match)**
    fully_identical_mask = df.duplicated(keep=False)
    duplicates["fully_identical_rows"] = df[fully_identical_mask].to_dict(orient="records")

    #print("\nDEBUG: Fully Identical Rows Detected:")
    #print(duplicates["fully_identical_rows"])


    # 2️⃣ **Detect: Same Order ID, but Different Attributes**
    same_order_id_diff_attributes = []
    grouped_by_order_id = df.groupby("Order ID")

    for order_id, group in grouped_by_order_id:
        if len(group) > 1:  # More than one row with the same Order ID
            unique_attributes = group[attributes_without_order_id].drop_duplicates()
            if len(unique_attributes) > 1:  # At least one attribute differs
                same_order_id_diff_attributes.extend(group.to_dict(orient="records"))

    duplicates["same_order_id_diff_attributes"] = same_order_id_diff_attributes

    # 3️⃣ **Detect: Same Attributes, but Different Order ID**
    same_attributes_diff_order_id = []
    grouped_by_attributes = df.groupby(attributes_without_order_id)

    for _, group in grouped_by_attributes:
        unique_order_ids = group["Order ID"].unique()
        if len(unique_order_ids) > 1:  # At least one Order ID differs
            same_attributes_diff_order_id.extend(group.to_dict(orient="records"))

    duplicates["same_attributes_diff_order_id"] = same_attributes_diff_order_id

    return duplicates


def detect_missing_values(df):
    """Detect missing values in the DataFrame based on report type (refund or payout)"""
    num_columns = len(df.columns)
    report_type = "refund" if num_columns == 6 else "payout" if num_columns == 8 else "unknown"
    
    if report_type == "unknown":
        return {"error": "Invalid report format. Expected 6 (refund) or 8 (payout) columns."}
    
    missing_info = []

    #print("DEBUG: Checking missing values...")

    for col_idx, col_name in enumerate(df.columns):
        missing_rows = df[df[col_name].isnull()].index.tolist()
        for row_idx in missing_rows:
            missing_info.append({
                "report_type": report_type,
                "row": row_idx + 1,  # Adjusting for header row
                "column": col_idx + 1,
                "column_name": col_name
            })
    
    return missing_info




def detect_inaccuracies(df):
    """Detect negative values logic issues and misleading timestamps in refund or payout report."""
    num_columns = len(df.columns)
    report_type = "refund" if num_columns == 6 else "payout" if num_columns == 8 else "unknown"
    
    if report_type == "unknown":
        return {"error": "Invalid report format. Expected 6 (refund) or 8 (payout) columns."}

    inaccuracies = {
        'negative_values': [],
        'incorrect_amount_calculation': [],
        'misleading_timestamps': [],
        'wrong_status_value': [] 
    }

    column_list = df.columns.tolist()
    date_column = 'Return Request Date' if report_type == "refund" else 'Transaction Date'
    today = datetime.today().date()

    parsed_dates_with_index = []  # Store parsed date info for order check

    # Format & Typo, Logical Consistency - future date, Weekend check
    for idx, row in df.iterrows():
        timestamp_issues = []
        date_value = str(row[date_column]).strip()
        order_id = str(row.get('Order ID', f"row_{idx + 1}"))

        #print(f"[DEBUG] Row {idx+1} — Raw date value: '{date_value}'")

        parsed_date = None

        # Check 1: Format & Typo /
        if not is_valid_date(date_value):
            #print(f"[DEBUG] Row {idx + 1} — INVALID FORMAT detected")
            timestamp_issues.append({
                'issue': 'Invalid date format, expected YYYY-MM-DD',
                'column_name': date_column,
                'column': column_list.index(date_column) + 1,
                'row': idx + 1

                #'column': date_column,
                #'row': idx + 1,
                #'column_number': column_list.index(date_column) + 1
            })

        try:
            parsed_date = datetime.strptime(date_value, '%Y-%m-%d').date()
        except ValueError:
            parsed_date = None  # If it's still invalid, skip further parsing-dependent checks

        if parsed_date:
            parsed_dates_with_index.append((idx, parsed_date))

            # Check 2: Logical Consistency - future date /
            if parsed_date > today:
                timestamp_issues.append({
                    'issue': 'Date is in the future',
                    'column_name': date_column,
                    'column': column_list.index(date_column) + 1,
                    'row': idx + 1

                    #'column': date_column,
                    #'row': idx + 1,
                    #'column_number': column_list.index(date_column) + 1
                })

            # Check 3: Weekend check /
            if report_type == "payout" and parsed_date.weekday() >= 5:
                timestamp_issues.append({
                    'issue': 'Date falls on a weekend',
                    'column_name': date_column,
                    'column': column_list.index(date_column) + 1,
                    'row': idx + 1

                    #'column': date_column,
                    #'row': idx + 1,
                    #'column_number': column_list.index(date_column) + 1
                })

        if timestamp_issues:
            #inaccuracies['misleading_timestamps'][order_id] = timestamp_issues
            for issue in timestamp_issues:
                inaccuracies['misleading_timestamps'].append({
                    'order_id': order_id,
                    **issue
                })


    # Date is out of chronological order
    for i in range(1, len(parsed_dates_with_index)):
        prev_idx, prev_date = parsed_dates_with_index[i - 1]
        curr_idx, curr_date = parsed_dates_with_index[i]

        if curr_date < prev_date:
            order_id = str(df.iloc[curr_idx].get('Order ID', f"row_{curr_idx + 1}"))
            inaccuracies['misleading_timestamps'].append({
                'order_id': order_id,
                'issue': 'Date is out of chronological order',
                'column_name': date_column,
                'column': column_list.index(date_column) + 1,
                'row': curr_idx + 1

                #'column': date_column,
                #'row': curr_idx + 1,
                #'column_number': column_list.index(date_column) + 1
            })

    # Existing Refund Logic
    if report_type == "refund":
        # Wrong status value check
        for idx, row in df.iterrows():
            order_id = row['Order ID']
            refund_amount = float(row['Refund Amount'])
            return_status = str(row['Return Status']).strip().capitalize()  # Normalize case
            status_issues = []

            # R1: Refund > 0 → Status must be Approved or Completed
            if refund_amount > 0 and return_status not in ['Approved', 'Completed']:
                status_issues.append({
                    'issue': 'Refund > 0 but status not Approved/Completed',
                    'column_name': 'Return Status',
                    'column': column_list.index('Return Status') + 1,
                    'row': idx + 1

                    #'column': 'Return Status',
                    #'row': idx + 1,
                    #'column_number': column_list.index('Return Status') + 1
                })

            # R2: Rejected/Pending → Refund should be 0
            if  refund_amount == 0 and return_status not in ['Rejected', 'Pending']:
                status_issues.append({
                    'issue': 'Refund is 0 but status not Rejected/Pending',
                    'column_name': 'Return Status',
                    'column': column_list.index('Return Status') + 1,
                    'row': idx + 1

                    #'column': 'Return Status',
                    #'row': idx + 1,
                    #'column_number': column_list.index('Return Status') + 1
                })

            if status_issues:
                for issue in status_issues:
                    inaccuracies['wrong_status_value'].append({
                        'order_id': order_id,
                        **issue
                    })
        
        #negative value
        for idx, row in df.iterrows():
            order_id = row['Order ID']
            negative_issues = []
            refund_amount = float(row['Refund Amount'])

            if refund_amount < 0:
                negative_issues.append({
                    'column': 'Refund Amount',
                    'row': idx + 1,
                    'column_number': column_list.index('Refund Amount') + 1
                })

            if negative_issues:
                inaccuracies.setdefault('negative_values', [])
                for issue in negative_issues:
                    inaccuracies['negative_values'].append({
                        'row': issue['row'],
                        'column': issue['column_number'],
                        'column_name': issue['column'],
                        'report_type': report_type
                    })

    else:

        for idx, row in df.iterrows():
            mismatched_columns = []

            gross_sales = Decimal(str(row['Gross Sales Amount']))
            platform_fee_actual = Decimal(str(row['Platform Fees (commissions or service charges)']))
            transaction_fee_actual = Decimal(str(row['Transaction Fees']))
            shipping_fee_actual = Decimal(str(row['Shipping Fees']))
            refunds_issued = Decimal(str(row['Refunds Issued']))
            net_payout_actual = Decimal(str(row['Net Payout Amount']))

            expected_platform_fee = round_half_up(gross_sales * Decimal('0.05'), 2)
            expected_transaction_fee = round_half_up(gross_sales * Decimal('0.015'), 2)

            if gross_sales <= Decimal('150'):
                expected_shipping_fee = 4
            elif gross_sales <= Decimal('250'):
                expected_shipping_fee = 5
            else:
                expected_shipping_fee = 6

            expected_net_payout = round_half_up(
                gross_sales - (
                    platform_fee_actual +
                    transaction_fee_actual +
                    shipping_fee_actual +
                    refunds_issued
                ), 2
            )

            calculated_gross = round_half_up(
                platform_fee_actual +
                transaction_fee_actual +
                shipping_fee_actual +
                refunds_issued +
                net_payout_actual,
                2
            )

            if not is_close(float(platform_fee_actual), expected_platform_fee):
                mismatched_columns.append({
                    'column_name': 'Platform Fees (commissions or service charges)',
                    'column': column_list.index('Platform Fees (commissions or service charges)') + 1,
                    'row': idx + 1
                    
                })

            if not is_close(float(transaction_fee_actual), expected_transaction_fee):
                mismatched_columns.append({
                    'column_name': 'Transaction Fees',
                    'column': column_list.index('Transaction Fees') + 1,
                    'row': idx + 1
                })

            if not is_close(float(shipping_fee_actual), expected_shipping_fee):
                mismatched_columns.append({
                    'column_name': 'Shipping Fees',
                    'column': column_list.index('Shipping Fees') + 1,
                    'row': idx + 1
                })

            if not is_close(float(net_payout_actual), expected_net_payout):
                mismatched_columns.append({
                    'column_name': 'Net Payout Amount',
                    'column': column_list.index('Net Payout Amount') + 1,
                    'row': idx + 1,
                })

            if not is_close(float(gross_sales), calculated_gross):
                mismatched_columns.append({
                    'column_name': 'Gross Sales Amount',
                    'column': column_list.index('Gross Sales Amount') + 1,
                    'row': idx + 1
                })

            if mismatched_columns:
                order_id = row['Order ID']
                for issue in mismatched_columns:
                    inaccuracies['incorrect_amount_calculation'].append({
                        'order_id': order_id,
                        **issue
                    })

        negative_columns = [
            'Gross Sales Amount',
            'Platform Fees (commissions or service charges)',
            'Transaction Fees',
            'Shipping Fees',
            'Refunds Issued',
            'Net Payout Amount'
        ]

        for idx, row in df.iterrows():
            order_id = row['Order ID']
            negative_issues = []

            for col in negative_columns:
                value = float(row[col])
                if value < 0:
                    negative_issues.append({
                        'column': col,
                        'row': idx + 1,
                        'column_number': column_list.index(col) + 1
                    })

            if negative_issues:
                inaccuracies.setdefault('negative_values', [])
                for issue in negative_issues:
                    inaccuracies['negative_values'].append({
                        'row': issue['row'],
                        'column': issue['column_number'],
                        'column_name': issue['column'],
                        'report_type': report_type
                    })

    return inaccuracies




def process_data(file_path):
    try:
        df = load_file(file_path)

        # Extract headers and data
        headers = list(df.columns)
        extracted_data = df.values.tolist()  # Convert DataFrame rows into list format

        duplicates = detect_duplicates(df)
        missing_values = detect_missing_values(df)
        inaccuracies = detect_inaccuracies(df)

        result = {
            "headers": headers,
            "extracted_data": extracted_data,
            "duplicates": duplicates,
            "missing_values": missing_values,
            "inaccuracies": inaccuracies
        }

        # Convert NaN to None in nested structures before JSON encoding
        result = replace_nan_with_none(result)

        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({"error": "Usage: python cleanData.py <file_path>"}))
    else:
        process_data(sys.argv[1])
