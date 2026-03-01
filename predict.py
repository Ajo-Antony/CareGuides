import numpy as np
import pandas as pd
from sklearn.preprocessing import LabelEncoder
import time
from sklearn.model_selection import train_test_split
from flask import Flask, request, jsonify
from sklearn.ensemble import RandomForestClassifier
import joblib
import warnings
warnings.filterwarnings('ignore')

app = Flask(__name__)

# Load and train model
print("Loading dataset and training model...")
try:
    df = pd.read_csv("Autism.csv")
    print("Dataset loaded successfully")
except Exception as e:
    print(f"Error loading dataset: {e}")
    # Create dummy data if file not found
    data = {
        'A1': [0,1,1,0,1,0,1,0,0,1]*10,
        'A2': [0,1,0,0,1,0,0,1,0,0]*10,
        'A3': [0,0,0,1,0,0,1,0,0,1]*10,
        'A4': [0,0,0,1,1,0,1,0,0,1]*10,
        'A5': [0,0,0,1,1,1,1,0,0,1]*10,
        'A6': [0,1,0,1,1,0,1,1,0,1]*10,
        'A7': [1,1,1,1,0,1,0,1,1,1]*10,
        'A8': [1,0,1,1,1,1,0,1,0,1]*10,
        'A9': [0,0,0,1,1,1,1,1,0,1]*10,
        'A10': [1,0,1,1,0,1,0,1,1,1]*10,
        'Class/ASD Traits ': ['No','Yes','Yes','Yes','Yes','No','Yes','Yes','No','Yes']*10
    }
    df = pd.DataFrame(data)

# Preprocess
columns_to_drop = ['Age_Mons','Qchat-10-Score','Sex','Ethnicity','Jaundice','Family_mem_with_ASD','Case_No','Who completed the test']
for col in columns_to_drop:
    if col in df.columns:
        df.drop(col, axis=1, inplace=True)

le = LabelEncoder()
if 'Class/ASD Traits ' in df.columns:
    df['Class/ASD Traits '] = le.fit_transform(df['Class/ASD Traits '])
    print(f"Label mapping: 0 = No, 1 = Yes")

# Features
feature_columns = ["A9","A5","A6","A8","A1","A3","A7","A2","A4","A10"]
X_aut = df[feature_columns]
Y_aut = df['Class/ASD Traits ']

# Train model
X_train, X_test, Y_train, Y_test = train_test_split(X_aut, Y_aut, test_size=0.3, random_state=42)
model = RandomForestClassifier(n_estimators=100, random_state=42)
model.fit(X_train, Y_train)
accuracy = model.score(X_test, Y_test)
print(f"Model trained. Accuracy: {accuracy:.2%}")

# Save model
joblib.dump(model, 'random_forest_model.pkl')
print("Model saved as 'random_forest_model.pkl'")

@app.route("/")
def home():
    return jsonify({
        "message": "Autism Prediction API",
        "status": "running",
        "endpoints": {
            "/check": "Make prediction with A1-A10 parameters",
            "/health": "Health check",
            "/model-info": "Model information"
        }
    })

@app.route("/check")
def checkAutism():
    try:
        # Get parameters
        answers = []
        for i in range(1, 11):
            param = f'A{i}'
            if param in request.args:
                val = int(request.args.get(param))
                if val not in [0, 1]:
                    return jsonify({"error": f"{param} must be 0 or 1"}), 400
                answers.append(val)
            else:
                return jsonify({"error": f"Missing parameter: {param}"}), 400
        
        # Reorder for model
        answers_reordered = [
            answers[8], answers[4], answers[5], answers[7],
            answers[0], answers[2], answers[6], answers[1],
            answers[3], answers[9]
        ]
        
        # Predict
        X_pred = np.array([answers_reordered])
        prediction = model.predict(X_pred)[0]
        probability = model.predict_proba(X_pred)[0]
        confidence = max(probability) * 100
        
        result = {
            "success": True,
            "prediction": int(prediction),
            "prediction_label": "Yes" if prediction == 1 else "No",
            "confidence": round(confidence, 2),
            "probabilities": {
                "No": round(probability[0] * 100, 2),
                "Yes": round(probability[1] * 100, 2)
            },
            "answers": answers,
            "model_accuracy": round(accuracy, 4),
            "timestamp": time.strftime('%Y-%m-%d %H:%M:%S')
        }
        
        return jsonify(result)
        
    except Exception as e:
        return jsonify({"error": str(e), "success": False}), 500

@app.route("/health")
def health():
    return jsonify({"status": "healthy", "timestamp": time.strftime('%Y-%m-%d %H:%M:%S')})

if __name__ == "__main__":
    print("\n" + "="*50)
    print("Autism Prediction API Starting...")
    print(f"Model Accuracy: {accuracy:.2%}")
    print("Endpoint: http://localhost:5001/check")
    print("="*50)
    app.run(host="0.0.0.0", port=5001, debug=True)