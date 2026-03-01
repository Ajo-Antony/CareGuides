from flask import Flask, request, jsonify
import pandas as pd
import joblib

app = Flask(__name__)

# Load model and encoders
try:
    model = joblib.load("therapy_model.pkl")
    label_encoder = joblib.load("label_encoder.pkl")
    feedback_encoder = joblib.load("feedback_encoder.pkl")
    
    # Load dataset and get unique therapies
    df = pd.read_csv("autism_therapy_dataset.csv", sep='\t')
    df.columns = df.columns.str.strip()
    df["Past_Therapies"] = df["Past_Therapies"].fillna("")
    therapy_list = sorted(set(";".join(df["Past_Therapies"]).split(";")) - {""})
    
    print("Model loaded successfully")
    print("Available therapies:", therapy_list)
except Exception as e:
    print(f"Error loading model: {e}")
    model = None

@app.route("/predict", methods=['POST'])
def predict_therapy():
    try:
        if model is None:
            return jsonify({"error": "Model not loaded"}), 500
            
        data = request.get_json()
        
        print("Received data:", data)
        
        age = int(data['Age'])
        asd_level = int(data['ASD_Level'])
        speech_delay = int(data['Speech_Delay'])
        motor_delay = int(data['Motor_Delay'])
        feedback = data['Feedback']
        past_therapies = data.get('Past_Therapies', [])
        
        print(f"Processing: Age={age}, ASD={asd_level}, Speech={speech_delay}, Motor={motor_delay}")
        print(f"Past therapies: {past_therapies}")

        # Encode feedback
        feedback_encoded = feedback_encoder.transform([feedback])[0]

        # Build input feature vector
        features = {
            "Age": age,
            "ASD_Level": asd_level,
            "Speech_Delay": speech_delay,
            "Motor_Delay": motor_delay,
            "Feedback": feedback_encoded
        }

        for therapy in therapy_list:
            features[therapy] = 1 if therapy in past_therapies else 0

        df_input = pd.DataFrame([features])
        prediction = model.predict(df_input)[0]
        predicted_label = label_encoder.inverse_transform([prediction])[0]
        
        print(f"Prediction: {predicted_label}")

        return jsonify({"prediction": predicted_label})

    except Exception as e:
        print(f"Error in prediction: {e}")
        return jsonify({"error": str(e)}), 500

@app.route("/health", methods=['GET'])
def health():
    return jsonify({"status": "healthy", "model_loaded": model is not None})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5002, debug=True)