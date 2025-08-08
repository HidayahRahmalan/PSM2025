import os
import pandas as pd

# Load the latest reports
CLEANED_DIR = r"C:\xampp\htdocs\fyp\cleaned"
REFUND_REPORT_PREFIX = "c xlsx Refund Report"
PAYOUT_REPORT_PREFIX = "c xlsx Payout Report"

def find_latest_file(directory, filename_prefix):
    """Find the latest file matching the prefix."""
    supported_formats = [".csv", ".xls", ".xlsx"]
    latest_file = None
    latest_mtime = 0

    for file in os.listdir(directory):
        if file.startswith(filename_prefix) and file.endswith(tuple(supported_formats)):
            full_path = os.path.join(directory, file)
            file_mtime = os.path.getmtime(full_path)
            if file_mtime > latest_mtime:
                latest_mtime = file_mtime
                latest_file = full_path

    if latest_file:
        print(f"Loading file: {latest_file}")
        return latest_file
    else:
        raise FileNotFoundError(f"No valid file found for {filename_prefix} in {directory}")

def load_latest_report(directory, filename_prefix):
    """Load the latest refund or payout report."""
    latest_file = find_latest_file(directory, filename_prefix)
    return pd.read_csv(latest_file) if latest_file.endswith(".csv") else pd.read_excel(latest_file)

# Load refund and payout reports
refund_report = load_latest_report(CLEANED_DIR, REFUND_REPORT_PREFIX)
payout_report = load_latest_report(CLEANED_DIR, PAYOUT_REPORT_PREFIX)

def merge_duplicates(df):
    """Merge duplicates based on the report type (Refund or Payout)."""
    
    num_columns = df.shape[1]  # Count columns
    is_refund_report = num_columns == 6  # Refund reports have 6 columns
    is_payout_report = num_columns == 8  # Payout reports have 8 columns

    if is_refund_report:
        print("🔍 Processing as Refund Report...")
        df = df.groupby("Order ID", as_index=False).agg({
            "Product Name": "first",
            "Return Request Date": "min",  # Keep earliest request date
            "Refund Amount": "sum",  # Sum refund amounts
            "Reason for Return": "first",
            "Return Status": "first"
        })

    elif is_payout_report:
        print("🔍 Processing as Payout Report...")
        df = df.groupby("Order ID", as_index=False).agg({
            "Transaction Date": "min",  # Keep earliest transaction date
            "Gross Sales Amount": "sum",
            "Platform Fees (commissions or service charges)": "sum",
            "Transaction Fees": "sum",
            "Shipping Fees": "sum",
            "Refunds Issued": "sum",
            "Net Payout Amount": "sum"
        })

    else:
        raise ValueError("❌ Unsupported file format. Please check column structure.")

    print("✅ Merging Completed.")
    return df

# Merge duplicates in both reports
merged_refund_report = merge_duplicates(refund_report)
merged_payout_report = merge_duplicates(payout_report)


print("✅ Merged reports saved successfully.")
