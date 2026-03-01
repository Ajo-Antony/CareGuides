import numpy as np
import pandas as pd
from sklearn.preprocessing import LabelEncoder
import time
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.externals import joblib  # Import joblib for model persistence

# read data
df = pd.read_csv("Autism.csv")

# remove unwanted columns
df.drop(['Age_Mons', 'Qchat-10-Score', 'Sex', 'Ethnicity', 'Jaundice', 'Family_mem_with_ASD', 'Case_No', 'Who completed the test'], axis=1, inplace=True)

# Preprocessing features to get them ready for modeling through encoding categorical features
le = LabelEncoder()
columns = ['Class/ASD Traits ']
for col in columns:
    df[col] = le.fit_transform(df[col])

X_aut = df[["A9", "A5", "A6", "A8", "A1", "A3","A7", "A2", "A4","A10"]]
X_aut = np.array(X_aut)

Y_aut = df['Class/ASD Traits ']
Y_aut = np.array(Y_aut)
