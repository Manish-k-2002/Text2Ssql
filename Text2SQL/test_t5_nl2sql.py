import torch
from transformers import T5Tokenizer, T5ForConditionalGeneration

# =========================
# 1. USER SETTINGS
# =========================
MODEL_PATH = "./t5_nl2sql_final"
DEVICE     = "cuda" if torch.cuda.is_available() else "cpu"

# =========================
# 2. LOAD MODEL + TOKENIZER
# =========================
tokenizer = T5Tokenizer.from_pretrained(MODEL_PATH)
model = T5ForConditionalGeneration.from_pretrained(MODEL_PATH).to(DEVICE)

# =========================
# 3. GENERATE FUNCTION
# =========================
def generate_sql(question):
    input_text = "translate to SQL: " + question
    inputs = tokenizer(input_text, return_tensors="pt", padding=True, truncation=True).to(DEVICE)
    outputs = model.generate(**inputs, max_length=128)
    return tokenizer.decode(outputs[0], skip_special_tokens=True)

# =========================
# 4. USER INPUT LOOP
# =========================
print("✅ Model loaded. Type a natural language question (or 'exit' to quit).\n")

while True:
    question = input("Enter your question: ")
    if question.lower() in ["exit", "quit", "q"]:
        print("Exiting...")
        break
    sql = generate_sql(question)
    print("Predicted SQL:", sql)
    print("-" * 50)

