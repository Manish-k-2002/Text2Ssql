# train.py
import pandas as pd
import torch
from torch.utils.data import Dataset, DataLoader
from transformers import T5Tokenizer, T5ForConditionalGeneration, AdamW

# =========================
# 1. USER SETTINGS
# =========================
DATA_PATH   = "datasets.csv"   # your dataset file
TEXT_COL    = "natural_language_query"                # natural language column
SQL_COL     = "sql_query"                     # SQL column
MODEL_NAME  = "t5-base"                # or t5-small / t5-large
OUTPUT_DIR  = "./t5_nl2sql_final"
EPOCHS      = 5
BATCH_SIZE  = 8
LR          = 3e-4
DEVICE      = "cuda" if torch.cuda.is_available() else "cpu"

# =========================
# 2. DATASET CLASS
# =========================
class NL2SQLDataset(Dataset):
    def __init__(self, dataframe, tokenizer, max_len=128):
        self.df = dataframe
        self.tokenizer = tokenizer
        self.max_len = max_len

    def __len__(self):
        return len(self.df)

    def __getitem__(self, idx):
        question = "translate to SQL: " + str(self.df.iloc[idx][TEXT_COL])
        sql      = str(self.df.iloc[idx][SQL_COL])

        source = self.tokenizer(question, max_length=self.max_len,
                                padding="max_length", truncation=True, return_tensors="pt")
        target = self.tokenizer(sql, max_length=self.max_len,
                                padding="max_length", truncation=True, return_tensors="pt")

        source_ids = source["input_ids"].squeeze()
        source_mask = source["attention_mask"].squeeze()
        target_ids = target["input_ids"].squeeze()

        return {
            "input_ids": source_ids,
            "attention_mask": source_mask,
            "labels": target_ids,
        }

# =========================
# 3. LOAD DATA
# =========================
df = pd.read_csv(DATA_PATH)
train_size = int(0.8 * len(df))
train_df, val_df = df[:train_size], df[train_size:]

tokenizer = T5Tokenizer.from_pretrained(MODEL_NAME)

train_dataset = NL2SQLDataset(train_df, tokenizer)
val_dataset   = NL2SQLDataset(val_df, tokenizer)

train_loader = DataLoader(train_dataset, batch_size=BATCH_SIZE, shuffle=True)
val_loader   = DataLoader(val_dataset, batch_size=BATCH_SIZE)

# =========================
# 4. MODEL + OPTIMIZER
# =========================
model = T5ForConditionalGeneration.from_pretrained(MODEL_NAME).to(DEVICE)
optimizer = AdamW(model.parameters(), lr=LR)

# =========================
# 5. TRAINING LOOP
# =========================
for epoch in range(EPOCHS):
    model.train()
    total_loss = 0
    for batch in train_loader:
        optimizer.zero_grad()
        input_ids = batch["input_ids"].to(DEVICE)
        attention_mask = batch["attention_mask"].to(DEVICE)
        labels = batch["labels"].to(DEVICE)

        outputs = model(input_ids=input_ids,
                        attention_mask=attention_mask,
                        labels=labels)

        loss = outputs.loss
        loss.backward()
        optimizer.step()

        total_loss += loss.item()

    avg_train_loss = total_loss / len(train_loader)

    # Validation
    model.eval()
    val_loss = 0
    with torch.no_grad():
        for batch in val_loader:
            input_ids = batch["input_ids"].to(DEVICE)
            attention_mask = batch["attention_mask"].to(DEVICE)
            labels = batch["labels"].to(DEVICE)

            outputs = model(input_ids=input_ids,
                            attention_mask=attention_mask,
                            labels=labels)
            val_loss += outputs.loss.item()

    avg_val_loss = val_loss / len(val_loader)
    print(f"Epoch {epoch+1}/{EPOCHS} | Train Loss: {avg_train_loss:.4f} | Val Loss: {avg_val_loss:.4f}")

# =========================
# 6. SAVE MODEL
# =========================
model.save_pretrained(OUTPUT_DIR)
tokenizer.save_pretrained(OUTPUT_DIR)

print(f"✅ Training complete. Model saved to {OUTPUT_DIR}")
