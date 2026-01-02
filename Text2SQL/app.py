import torch
from flask import Flask, request, jsonify
from transformers import (
    T5Tokenizer, T5ForConditionalGeneration,
    EncoderDecoderModel, AutoTokenizer
)
from flask_cors import CORS

# =========================
# 1. SETTINGS
# =========================
T5_MODEL_PATH     = "./t5_nl2sql_final"      # trained T5 model
CODEBERT_MODEL    = "./codebert_nl2sql"  # trained CodeBERT encoder-decoder model
DEVICE            = "cuda" if torch.cuda.is_available() else "cpu"

# =========================
# 2. LOAD T5
# =========================
t5_tokenizer = T5Tokenizer.from_pretrained(T5_MODEL_PATH)
t5_model = T5ForConditionalGeneration.from_pretrained(T5_MODEL_PATH).to(DEVICE)

def generate_sql_t5(question: str) -> str:
    input_text = "translate to SQL: " + question
    inputs = t5_tokenizer(input_text, return_tensors="pt", padding=True, truncation=True).to(DEVICE)
    outputs = t5_model.generate(**inputs, max_length=128, num_beams=2, early_stopping=True)
    return t5_tokenizer.decode(outputs[0], skip_special_tokens=True)

# =========================
# 3. LOAD TRAINED CODEBERT ENCODER-DECODER
# =========================
codebert_tokenizer = AutoTokenizer.from_pretrained(CODEBERT_MODEL)
codebert_model = EncoderDecoderModel.from_pretrained(CODEBERT_MODEL).to(DEVICE)

def generate_sql_codebert(question: str) -> str:
    input_text = "translate to SQL: " + question
    inputs = codebert_tokenizer(input_text, return_tensors="pt", padding=True, truncation=True).to(DEVICE)
    outputs = codebert_model.generate(**inputs, max_length=128, num_beams=2, early_stopping=True)
    return codebert_tokenizer.decode(outputs[0], skip_special_tokens=True)

# =========================
# 4. FLASK APP
# =========================
app = Flask(__name__)
CORS(app)

@app.route("/", methods=["GET"])
def home():
    return "<h3>NL → SQL API Running</h3>"

@app.route("/nl2sql", methods=["POST"])
def nl2sql():
    data = request.get_json()
    question = data.get("query", "")
    model_used = data.get("model", "T5")

    if not question.strip():
        return jsonify({"error": "Empty query"}), 400

    if model_used == "CodeBERT":
        sql_query = generate_sql_codebert(question)
        return jsonify({
            "model": "CodeBERT",
            "input": question,
            "sql": sql_query
        })
    else:  # default = T5
        sql_query = generate_sql_t5(question)
        return jsonify({
            "model": "T5",
            "input": question,
            "sql": sql_query
        })

if __name__ == "__main__":
    app.run(debug=True, port=5000)
