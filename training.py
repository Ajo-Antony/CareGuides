import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.ensemble import RandomForestClassifier
import joblib

# Load dataset (Tab-separated)
df = pd.read_csv("autism_therapy_dataset.csv", sep='\t')
df.columns = df.columns.str.strip()

# Debug: Check columns
print("Columns:", df.columns.tolist())

# Fix: Fill missing therapy strings
df["Past_Therapies"] = df["Past_Therapies"].fillna("")

# Ensure correct datatypes
df["ASD_Level"] = df["ASD_Level"].astype(int)
df["Speech_Delay"] = df["Speech_Delay"].astype(int)
df["Motor_Delay"] = df["Motor_Delay"].astype(int)

# Extract unique therapies
therapy_split = df["Past_Therapies"].str.split(";")
therapy_set = set()
for t in therapy_split:
    therapy_set.update([i.strip() for i in t if i.strip()])
therapy_list = sorted(list(therapy_set))

# Create binary columns
for therapy in therapy_list:
    df[therapy] = therapy_split.apply(lambda x: 1 if therapy in [t.strip() for t in x] else 0)

# Encode Feedback
feedback_encoder = LabelEncoder()
df["Feedback"] = feedback_encoder.fit_transform(df["Feedback"])

# Encode Effective_Therapy
therapy_encoder = LabelEncoder()
df["Effective_Therapy"] = therapy_encoder.fit_transform(df["Effective_Therapy"])

# Save encoders
joblib.dump(therapy_encoder, "label_encoder.pkl")
joblib.dump(feedback_encoder, "feedback_encoder.pkl")

# Features
features = ["Age", "ASD_Level", "Speech_Delay", "Motor_Delay", "Feedback"] + therapy_list
X = df[features]
Y = df["Effective_Therapy"]

# Train/test split
X_train, X_test, Y_train, Y_test = train_test_split(X, Y, test_size=0.3, random_state=42)

# Train model
model = RandomForestClassifier(n_estimators=100, random_state=42)
model.fit(X_train, Y_train)

# Accuracy
accuracy = model.score(X_test, Y_test)
print("Model accuracy:", round(accuracy * 100, 2), "%")

# Save model
joblib.dump(model, "therapy_model.pkl")
