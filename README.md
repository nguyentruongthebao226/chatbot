📘 *English version below*
# 📚 Chatbot tài liệu nội bộ - Internal Document Chatbot (Laravel + OpenAI + Qdrant)

Hệ thống chatbot nội bộ sử dụng tài liệu công ty để trả lời câu hỏi. Chatbot **chỉ dựa trên tài liệu đã train**, không được phép sử dụng kiến thức bên ngoài.

---

## 🧩 Công nghệ sử dụng

| Thành phần     | Công nghệ             |
|----------------|------------------------|
| Backend        | Laravel 10             |
| Trí tuệ nhân tạo | OpenAI GPT-3.5 (chat), OpenAI Embeddings |
| Vector Database | Qdrant                 |
| Trích xuất PDF | smalot/pdfparser       |
| Trích xuất Word | phpoffice/phpword     |
| Giao tiếp HTTP | guzzlehttp/guzzle     |
| Lưu log        | Eloquent (MySQL / SQLite) |

🔹 Lý do chọn Qdrant thay vì MySQL
| Tính năng                                  | Qdrant (Vector DB)                     | MySQL (RDBMS truyền thống)                    |
|--------------------------------------------|----------------------------------------|-----------------------------------------------|
| **Lưu vector số học (embedding)**          | ✅ Thiết kế chuyên biệt                | ⚠️ Lưu dạng JSON hoặc TEXT, không tối ưu       |
| **Tìm kiếm ngữ nghĩa (semantic similarity)**| ✅ Có sẵn cosine / dot product         | ❌ Không hỗ trợ, cần code thủ công             |
| **Top-k nearest neighbors (ANN)**          | ✅ Rất nhanh với cấu trúc HNSW/IVF... | ❌ Phải load toàn bộ dữ liệu để so sánh        |
| **Khả năng mở rộng hàng triệu vector**     | ✅ Rất tốt, hiệu suất cao              | ❌ Chậm và nặng (dữ liệu dạng TEXT/JSON)       |
| **API hỗ trợ vector search**               | ✅ RESTful / GRPC có sẵn               | ❌ Không có                                    |
| **Hỗ trợ metadata**                        | ✅ Gắn được text, ID, file,...         | ⚠️ Có nhưng không liên kết với vector          |
| **Ứng dụng trong AI / Chatbot**            | ✅ Chuẩn RAG, AI Search                | ❌ Không phù hợp                                |

⚡ So sánh hiệu năng khi xử lý embedding
| Tiêu chí                                      | Qdrant (Vector DB)                          | MySQL (lưu embedding JSON)                   |
|----------------------------------------------|---------------------------------------------|----------------------------------------------|
| **Tối ưu cho vector search**                 | ✅ Có (ANN index, HNSW, IVF...)             | ❌ Không có                                   |
| **Tìm top-k gần nhất (cosine/dot-product)**  | ✅ Chỉ vài ms                               | ❌ Rất chậm nếu > 5000 rows                   |
| **Index cho vector**                         | ✅ Có sẵn                                    | ❌ Không có                                   |
| **Scale lớn**                                | ✅ Mượt với hàng triệu vector               | ❌ Query nặng nếu số rows lớn                 |
| **Query thời gian thực (chatbot)**           | ✅ Tối ưu realtime                          | ❌ Dễ lag nếu so sánh embedding bằng PHP      |


---

## 📦 Triển khai & Môi trường

**.env mẫu:**
```env
OPENAI_API_KEY=your_openai_key_here
QDRANT_HOST=http://localhost:6333
```
---

## 👨‍💻 Ghi chú 

- Bot hoạt động hiệu quả khi dữ liệu đã được **chunk đều**, tránh quá dài (>200 từ)
- Tài liệu HTML / URL cần được **lọc bỏ script, style, meta, inline css/js**
- Có thể dễ dàng **thêm model ChatLog theo người dùng** nếu tích hợp hệ thống đăng nhập
- Tránh gửi những câu hỏi nằm ngoài context đã train — bot sẽ không trả lời chính xác

---

## ✅ Tính năng chính

- 🤖 Trả lời câu hỏi dựa trên nội dung PDF, DOCX, CSV, HTML, URL
- 📎 Hỗ trợ trích xuất nội dung, chia nhỏ và nhúng vào Qdrant
- 🧠 Nhận biết câu hỏi tương tự để trả lời nhất quán
- 🛑 Không bịa thông tin, nếu không tìm thấy nội dung → báo lỗi rõ ràng
- 💬 Sử dụng OpenAI (GPT-3.5/4) để tạo câu trả lời dựa trên context

---

## ⚙️ Quy trình hoạt động

### 1. Đưa tài liệu vào hệ thống (Train)

1. **Upload tài liệu** → lưu vào `storage/app/`
2. **Trích xuất nội dung** với `DocumentParser`
3. **Chia nhỏ (chunk)** văn bản bằng `TextChunker`
4. **Tạo embedding** từ mỗi đoạn văn bản (chunk) bằng OpenAI
5. **Lưu vào Qdrant** cùng với `document_id`, `chunk_index`, `text`

### 2. Trả lời câu hỏi người dùng

1. **Nhúng embedding** từ câu hỏi người dùng
2. **So sánh với các câu hỏi cũ** trong `chat_logs`
    - Nếu khoảng cách cosine < 0.04 → trả câu trả lời cũ (giữ sự nhất quán)
3. **Tìm kiếm các đoạn văn gần nhất** trong Qdrant
4. **Nếu không có đoạn phù hợp** → báo lỗi: [StatusCode=404] Xin lỗi, tôi không tìm thấy câu trả lời phù hợp trong tài liệu.
5. **Gửi context + câu hỏi vào OpenAI GPT**
6. **Lưu log câu hỏi, embedding, câu trả lời**

---

## 🧱 Cấu trúc thư mục chính

| File / Class                     | Vai trò                                                                 |
|----------------------------------|------------------------------------------------------------------------|
| `ChatController`                | Xử lý quá trình hỏi đáp và logic kiểm tra context                      |
| `DocumentParser`                | Trích xuất nội dung từ PDF, DOCX, CSV, HTML, URL                       |
| `TextChunker`                   | Chia nhỏ văn bản thành các đoạn nhỏ để train                           |
| `Embedder`                      | Gọi OpenAI để tạo embedding vector                                     |
| `QdrantService`                 | Giao tiếp Qdrant: tạo collection, insert point, search                 |
| `ChatLog`                       | Model lưu lịch sử câu hỏi, câu trả lời và vector                       |

---

## 🔄 API & Routes

### 🧠 Giao tiếp chính

- `POST /api/ask`  
Gửi câu hỏi → chatbot trả lời  
**Body:** `{ "question": "..." }`  
**Response:** `{ "answer": "..." }`

### 📄 Train tài liệu

- `GET /train/{id}`  
Train nội dung tài liệu từ DB

- `GET /train-url?url=https://...`  
Train nội dung từ một trang web (chỉ phần nội dung text)

### 🛠 Kiểm tra, debug

- `GET /test-read/{id}` – đọc nội dung file
- `GET /test-chunk/{id}` – xem chunk preview
- `GET /test-embed` – thử embedding
- `GET /create-collection` – tạo collection mới
- `GET /reindex` – tạo lại index trong Qdrant
- `GET /debug-vectors/{id}` – xem điểm vector lưu trong Qdrant

---

## 📌 Lưu ý

- Bot **chỉ trả lời trong tài liệu**. Nếu không có context → trả lỗi rõ ràng và có throw "source" hoặc StatusCode=404 để bắt modal chat admin.
- Với câu hỏi giống nhau hoặc gần giống → hệ thống sẽ trả về cùng 1 câu trả lời cũ (nếu đã từng được hỏi).
- Nếu tài liệu là HTML/URL, hệ thống **tự động lọc bỏ JS, CSS, meta** để tiết kiệm tài nguyên và tránh nhiễu.

---

## 🧠 Logic so khớp câu hỏi cũ

```php
foreach ($pastLogs as $log) {
 $pastVector = json_decode($log->embedding, true);
 $distance = cosineDistance($newEmbedding, $pastVector);
 if ($distance < 0.04) {
     return $log->answer;
 }
}
```


# 📘 Postman API Collection - `chatbot_qdrant`

Bộ API hỗ trợ upload tài liệu, trích xuất văn bản, sinh vector embedding, train vào Qdrant, và truy vấn qua chatbot AI.

---

## 🔹 Upload & Trích xuất nội dung

### `POST /api/upload`
- **Chức năng**: Upload tài liệu (PDF, DOCX, CSV, HTML) để xử lý.
- **Body**: `form-data`  
  - `file`: File tài liệu cần upload

---

### `GET /test-read/{id}`
- **Chức năng**: Đọc nội dung văn bản từ tài liệu đã upload theo `document_id`.

---

### `GET /test-url?url=...`
- **Chức năng**: Trích xuất văn bản từ một đường dẫn URL bất kỳ (dùng cho web html).

---

## 🔹 Chunk văn bản & Tạo Embedding

### `GET /test-chunk/{id}`
- **Chức năng**: Cắt văn bản từ tài liệu thành các đoạn nhỏ (chunk) để xử lý embedding.

---

### `GET /test-chunk-url?url=...`
- **Chức năng**: Cắt văn bản từ một URL thành các đoạn nhỏ để xử lý.

---

### `GET /test-embed`
- **Chức năng**: Test tạo embedding vector cho một đoạn văn bản mẫu.

---

## 🔹 Tạo & Train dữ liệu vào Qdrant

### `GET /create-collection`
- **Chức năng**: Tạo collection `doc_chunks` trong Qdrant để lưu vector.

---

### `GET /train/{id}`
- **Chức năng**: Train nội dung tài liệu (từ database) vào Qdrant.

---

### `GET /train-url?url=...`
- **Chức năng**: Train nội dung từ trang web (URL) vào Qdrant.

---

## 🔹 Gửi câu hỏi tới chatbot

### `POST /api/chat`
- **Chức năng**: Gửi câu hỏi đến AI và nhận lại câu trả lời từ tài liệu đã train.
- **Body**: `form-data`  
  - `question`: Câu hỏi cần tìm câu trả lời.

---

## 🔹 Quản lý dữ liệu Qdrant

### `GET /reindex`
- **Chức năng**: Reindex lại collection trong Qdrant sau khi cập nhật dữ liệu mới.

---

### `GET http://localhost:6333/collections/doc_chunks`
- **Chức năng**: Kiểm tra metadata của collection `doc_chunks`.

---

### `GET http://localhost:6333/collections/doc_chunks/points/{id}`
- **Chức năng**: Kiểm tra chi tiết một điểm (vector) đã lưu theo ID.

---

### `POST http://localhost:6333/collections/doc_chunks/points/scroll`
- **Chức năng**: Scroll qua toàn bộ points trong collection.

---

### `DELETE http://localhost:6333/collections/doc_chunks`
- **Chức năng**: Xoá toàn bộ collection và dữ liệu trong Qdrant (Dùng cẩn thận!).

---

## 📌 Ghi chú

- Các API sử dụng nội bộ qua `localhost`, cần đảm bảo Laravel (port 8000) và Qdrant (port 6333) đều đang chạy.
- Tài liệu sẽ được chia thành nhiều đoạn nhỏ (~200 từ) trước khi embedding vào Qdrant.
- Chatbot chỉ trả lời dựa trên tài liệu đã train. Không bịa thông tin bên ngoài.

--- 
---
---
---

# 📘 English Version

## 🤖 Internal Document Chatbot (Laravel + OpenAI + Qdrant)

This chatbot system is designed for internal document-based Q&A. It **only answers based on trained documents**, without using external knowledge sources.

### 🧩 Technologies Used

| Component         | Technology                         |
|-------------------|-------------------------------------|
| Backend           | Laravel 10                          |
| AI Chat & Embedding | OpenAI GPT-3.5 / GPT-4            |
| Vector DB         | Qdrant                              |
| PDF Parser        | smalot/pdfparser                    |
| Word Parser       | phpoffice/phpword                   |
| HTTP              | guzzlehttp/guzzle                   |
| Logging           | Eloquent (MySQL / SQLite)           |

### ✅ Key Features

- Answer questions based on internal files: PDF, DOCX, CSV, HTML, URL
- Automatic text extraction and chunking for embeddings
- Embedding vector search using Qdrant ANN
- Avoids hallucination — throws clear error when no match found
- Uses OpenAI GPT to generate final answers

### ⚙️ Workflow

1. Upload document
2. Parse & split into chunks
3. Generate embedding via OpenAI
4. Store in Qdrant
5. At question time: embed → search Qdrant → send to GPT → respond

### 📈 Why Qdrant over MySQL?

- Optimized for vector search (cosine, dot-product)
- Fast real-time response even with millions of vectors
- Supports ANN index, metadata, REST API
- MySQL is slower, lacks native vector search

> See full comparison tables above ☝️

### 🔗 API Examples

- `POST /api/chat` – Ask a question
- `GET /train/{id}` – Train uploaded document
- `GET /test-url?url=...` – Parse content from website
- `GET /create-collection` – Create Qdrant collection
- `GET /reindex` – Rebuild vector index

---
