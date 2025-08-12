#!/usr/bin/env python3
"""
Python Random Forest implementation for Hostel Demand Prediction
Maintains all 9 factors from the PHP implementation
"""

import sys
import json
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import mean_squared_error, r2_score
import pickle
import os
from datetime import datetime

def load_and_preprocess_data(csv_path):
    """Load and preprocess CSV data for ML training"""
    try:
        # Load CSV data
        df = pd.read_csv(csv_path)
        
        # Ensure all required columns exist (matching actual CSV format)
        required_columns = [
            'Semester', 'Year', 'Hostel', 'Total_Severe_Chronic_Students', 
            'Booked_Severe_Chronic_Students', 'Room_Full_Rejections', 
            'Unbooked_Severe_Chronic_Students', 'Graduating_Students', 
            'Current_Occupancy', 'Actual_Demand'
        ]
        
        missing_columns = [col for col in required_columns if col not in df.columns]
        if missing_columns:
            return {"success": False, "message": f"Missing columns: {missing_columns}"}
        
        # Create hostel mapping (same as PHP)
        unique_hostels = df['Hostel'].unique()
        hostel_mapping = {hostel: idx for idx, hostel in enumerate(unique_hostels)}
        
        # Extract year components (same as PHP)
        df['year_start'] = df['Year'].str.split('/').str[0].astype(int)
        
        # Create features array (same 9 factors as PHP)
        features = []
        targets = []
        
        for idx, row in df.iterrows():
            feature = {
                'semester': int(row['Semester']),
                'year': int(row['year_start']),
                'hostel_id': hostel_mapping[row['Hostel']],
                'total_severe_chronic': int(row['Total_Severe_Chronic_Students']),
                'booked_severe_chronic': int(row['Booked_Severe_Chronic_Students']),
                'room_full_rejections': int(row['Room_Full_Rejections']),
                'unbooked_severe_chronic': int(row['Unbooked_Severe_Chronic_Students']),
                'graduating_students': int(row['Graduating_Students']),
                'current_occupancy': int(row['Current_Occupancy'])
            }
            
            features.append(feature)
            targets.append(int(row['Actual_Demand']))
        
        return {
            "success": True,
            "features": features,
            "targets": targets,
            "hostel_mapping": hostel_mapping,
            "data_count": len(features)
        }
        
    except Exception as e:
        return {"success": False, "message": f"Error loading data: {str(e)}"}

def train_random_forest(features, targets, n_trees=100, max_depth=10, model_type="simple"):
    """Train Random Forest model with scikit-learn"""
    try:
        # Convert features to DataFrame
        feature_names = ['semester', 'year', 'hostel_id', 'total_severe_chronic', 
                        'booked_severe_chronic', 'room_full_rejections', 
                        'unbooked_severe_chronic', 'graduating_students', 'current_occupancy']
        
        X = pd.DataFrame(features, columns=feature_names)
        y = np.array(targets)
        
        # Split data for validation
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
        
        # Train Random Forest
        rf_model = RandomForestRegressor(
            n_estimators=n_trees,
            max_depth=max_depth,
            random_state=42,
            n_jobs=-1  # Use all CPU cores
        )
        
        rf_model.fit(X_train, y_train)
        
        # Evaluate model
        y_pred = rf_model.predict(X_test)
        mse = mean_squared_error(y_test, y_pred)
        r2 = r2_score(y_test, y_pred)
        
        # Cross-validation score
        cv_scores = cross_val_score(rf_model, X, y, cv=5, scoring='r2')
        
        # Feature importance
        feature_importance = dict(zip(feature_names, rf_model.feature_importances_))
        
        # Save model to different files based on type
        if model_type == "advanced":
            model_path = 'models/random_forest_advanced_model.pkl'
        else:
            model_path = 'models/random_forest_model.pkl'
        os.makedirs('models', exist_ok=True)
        
        with open(model_path, 'wb') as f:
            pickle.dump({
                'model': rf_model,
                'feature_names': feature_names,
                'hostel_mapping': None,  # Will be set separately
                'training_date': datetime.now().isoformat(),
                'model_params': {
                    'n_trees': n_trees,
                    'max_depth': max_depth
                }
            }, f)
        
        return {
            "success": True,
            "model_path": model_path,
            "feature_importance": feature_importance,
            "evaluation": {
                "mse": float(mse),
                "r2_score": float(r2),
                "cv_mean": float(cv_scores.mean()),
                "cv_std": float(cv_scores.std())
            },
            "data_info": {
                "training_samples": len(X_train),
                "test_samples": len(X_test),
                "total_samples": len(X),  # Total samples before split
                "total_features": len(feature_names)
            }
        }
        
    except Exception as e:
        return {"success": False, "message": f"Error training model: {str(e)}"}

def make_prediction(model_path, features, hostel_mapping):
    """Make prediction using trained Random Forest model"""
    try:
        # Load model
        with open(model_path, 'rb') as f:
            model_data = pickle.load(f)
        
        rf_model = model_data['model']
        feature_names = model_data['feature_names']
        
        # Convert hostel name to ID if needed
        if 'hostel_name' in features:
            hostel_name = features['hostel_name']
            if hostel_name in hostel_mapping:
                features['hostel_id'] = hostel_mapping[hostel_name]
            else:
                return {"success": False, "message": f"Unknown hostel: {hostel_name}"}
        
        # Ensure all features are present
        required_features = ['semester', 'year', 'hostel_id', 'total_severe_chronic', 
                           'booked_severe_chronic', 'room_full_rejections', 
                           'unbooked_severe_chronic', 'graduating_students', 'current_occupancy']
        
        missing_features = [f for f in required_features if f not in features]
        if missing_features:
            return {"success": False, "message": f"Missing features: {missing_features}"}
        
        # Create feature array in correct order
        feature_array = [features[f] for f in feature_names]
        
        # Make prediction
        prediction = rf_model.predict([feature_array])[0]
        
        # Get prediction confidence (using tree variance)
        predictions = []
        for tree in rf_model.estimators_:
            predictions.append(tree.predict([feature_array])[0])
        
        confidence = 1.0 - (np.std(predictions) / (np.mean(predictions) + 1e-8))
        confidence = max(0.0, min(1.0, confidence))  # Clamp between 0 and 1
        
        return {
            "success": True,
            "prediction": float(prediction),
            "confidence": float(confidence),
            "feature_importance": dict(zip(feature_names, rf_model.feature_importances_))
        }
        
    except Exception as e:
        return {"success": False, "message": f"Error making prediction: {str(e)}"}

def main():
    """Main function to handle command line arguments"""
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "message": "Usage: python ml_random_forest.py [train|predict] [args...]"}))
        return
    
    action = sys.argv[1]
    
    if action == "train":
        if len(sys.argv) < 3:
            print(json.dumps({"success": False, "message": "Usage: python ml_random_forest.py train <csv_path> [n_trees] [max_depth] [model_type]"}))
            return
        
        csv_path = sys.argv[2]
        n_trees = int(sys.argv[3]) if len(sys.argv) > 3 else 100
        max_depth = int(sys.argv[4]) if len(sys.argv) > 4 else 10
        model_type = sys.argv[5] if len(sys.argv) > 5 else "simple"
        
        # Load and preprocess data
        data_result = load_and_preprocess_data(csv_path)
        if not data_result["success"]:
            print(json.dumps(data_result))
            return
        
        # Train model
        train_result = train_random_forest(
            data_result["features"], 
            data_result["targets"], 
            n_trees, 
            max_depth,
            model_type
        )
        
        if train_result["success"]:
            # Add hostel mapping to saved model
            model_path = train_result["model_path"]
            with open(model_path, 'rb') as f:
                model_data = pickle.load(f)
            
            model_data['hostel_mapping'] = data_result["hostel_mapping"]
            
            with open(model_path, 'wb') as f:
                pickle.dump(model_data, f)
            
            train_result["hostel_mapping"] = data_result["hostel_mapping"]
        
        print(json.dumps(train_result))
        
    elif action == "predict":
        if len(sys.argv) < 4:
            print(json.dumps({"success": False, "message": "Usage: python ml_random_forest.py predict <model_path> <features_json> <hostel_mapping_json>"}))
            return
        
        model_path = sys.argv[2]
        
        # Handle both file paths and JSON strings
        try:
            if sys.argv[3].endswith('.json'):
                with open(sys.argv[3], 'r') as f:
                    features = json.load(f)
            else:
                features = json.loads(sys.argv[3])
        except Exception as e:
            return
            
        try:
            if sys.argv[4].endswith('.json'):
                with open(sys.argv[4], 'r') as f:
                    hostel_mapping = json.load(f)
            else:
                hostel_mapping = json.loads(sys.argv[4])
        except Exception as e:
            return
        
        prediction_result = make_prediction(model_path, features, hostel_mapping)
        print(json.dumps(prediction_result))
        
    else:
        print(json.dumps({"success": False, "message": f"Unknown action: {action}"}))

if __name__ == "__main__":
    main() 