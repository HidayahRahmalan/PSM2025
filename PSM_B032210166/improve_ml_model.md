# ML Model Improvement Action Plan

## STEP 1: Data Collection (CRITICAL)
### Target: 200+ rows minimum

**Option A: Historical Data**
- Collect data from 2020-2024 (4+ years)
- Include all hostels in system
- Add monthly/seasonal variations

**Option B: Synthetic Data Generation**
- Create realistic variations of existing patterns
- Use domain knowledge to generate plausible scenarios
- Ensure data follows real-world constraints

## STEP 2: Data Quality Fixes

### Fix Hostel Name Consistency
```sql
-- Standardize all hostel names to uppercase
UPDATE CSV_DATA SET Hostel = UPPER(TRIM(Hostel))
```

### Add More Features
- Previous semester demand
- Student population trends
- Academic calendar factors
- Economic indicators

## STEP 3: Model Configuration Changes

### Use Simpler Models Initially
```python
# Try Linear Regression first
from sklearn.linear_model import LinearRegression
model = LinearRegression()

# If Random Forest, use conservative settings
RandomForestRegressor(
    n_estimators=50,
    max_depth=3,
    min_samples_split=5,
    min_samples_leaf=2
)
```

## STEP 4: Validation Strategy

### Cross-Validation
```python
# Use Leave-One-Out CV for small datasets
from sklearn.model_selection import LeaveOneOut
cv = LeaveOneOut()
scores = cross_val_score(model, X, y, cv=cv)
```

### Performance Targets
- **R² > 0.5**: Acceptable performance
- **R² > 0.7**: Good performance  
- **R² > 0.8**: Excellent performance

## STEP 5: Feature Importance Analysis

```python
# Identify most important features
feature_importance = model.feature_importances_
important_features = sorted(zip(feature_names, feature_importance), 
                          key=lambda x: x[1], reverse=True)
```

## Expected Improvements

**Current**: R² = -0.03 (Very Poor)
**With 50+ rows**: R² = 0.3-0.5 (Acceptable)
**With 200+ rows**: R² = 0.6-0.8 (Good to Excellent)
**With feature engineering**: R² = 0.7-0.9 (Excellent)