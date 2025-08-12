import os
import pandas as pd


def get_next_versioned_filename(filename):
    """Generate a new versioned filename to avoid overwriting."""
    base, ext = os.path.splitext(filename)
    version = 1

    while os.path.exists(f"{base}_v{version}{ext}"):
        version += 1

    return f"{base}_v{version}{ext}"




def infer_valid_order_ids(data):
    """Infer the most reliable order ID based on frequency and correct position."""
    order_id_counts = data["order_id"].value_counts()

    # Step 1: Select the most frequent order ID
    most_frequent_id = order_id_counts.idxmax()

    # Step 2: Find the correct position of this ID
    position_of_most_frequent = data[data["order_id"] == most_frequent_id]["row"].idxmin()

    return most_frequent_id, position_of_most_frequent


def merge_rows_diff_order(data, report_type):
    print(" rdoipy report_type:", report_type)
    """Merge duplicate rows with different Order IDs and maintain correct position."""

    # ✅ Normalize column names
    data.columns = data.columns.str.strip().str.lower().str.replace(" ", "_")

    if report_type == "refund":
        key_columns = ["product_name", "return_request_date", "refund_amount", "reason_for_return", "return_status"]
    elif report_type == "payout":
        key_columns = ["transaction_date", "gross_sales_amount", "platform_fees_(commissions_or_service_charges)", "transaction_fees", 
                       "shipping_fees", "refunds_issued", "net_payout_amount"]
            
        # Ensure platform_fees_(commissions_or_service_charges) is always present
        if "platform_fees_(commissions_or_service_charges)" not in data.columns:
            raise KeyError("Missing 'platform_fees_(commissions_or_service_charges)' in input data.")
    
    else:
        raise ValueError("Invalid report type! Use 'refund' or 'payout'.")

    if "row" not in data.columns:
        raise KeyError("Missing 'row' column in input data.")

    grouped = data.groupby(key_columns, as_index=False)
    merged_data = []
    
    for _, group in grouped:
        if len(group) > 1:
            # Infer the most reliable order ID and its correct position
            correct_order_id, correct_position = infer_valid_order_ids(group)

            # Select the row at the correct position
            best_match_row = group.loc[correct_position].copy()

            # Merge row numbers to track history
            best_match_row["row"] = sorted(group["row"].tolist())

            # Use the most reliable order ID
            best_match_row["order_id"] = correct_order_id

            merged_data.append(best_match_row)
        else:
            merged_data.append(group.iloc[0])

    merged_df = pd.DataFrame(merged_data)

    # Sort by the correct row position
    merged_df = merged_df.sort_values(by="row", key=lambda x: x.apply(lambda r: min(r) if isinstance(r, list) else r))

    return merged_df
