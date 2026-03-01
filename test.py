from flask import Flask, request, jsonify
import joblib
import pandas as pd

app = Flask(__name__)

# Load model and encoders
model = joblib.load("therapy_model.pkl")
label_encoder = joblib.load("label_encoder.pkl")
feedback_encoder = joblib.load("feedback_encoder.pkl")

# Load therapy list
df = pd.read_csv("autism_therapy_dataset.csv")
df.columns = df.columns.str.strip()
df["Past_Therapies"] = df["Past_Therapies"].fillna("")
therapy_list = sorted(set(";".join(df["Past_Therapies"]).split(";")) - {""})

@app.route('/predict', methods=['POST'])
def predict():
    data = request.get_json()

    input_data = {
        'Age': data['Age'],
        'ASD_Level': int(data['ASD_Level']),
        'Speech_Delay': int(data['Speech_Delay']),
        'Motor_Delay': int(data['Motor_Delay']),
        'Feedback': feedback_encoder.transform([data['Feedback']])[0]
    }

    for therapy in therapy_list:
        input_data[therapy] = 1 if therapy in data.get("Past_Therapies", []) else 0

    df_input = pd.DataFrame([input_data])
    prediction = model.predict(df_input)[0]
    predicted_label = label_encoder.inverse_transform([prediction])[0]

    return jsonify({'prediction': predicted_label})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5002)
