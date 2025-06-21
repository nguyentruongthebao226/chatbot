📘 *English version below*
# 📚 Chatbot tài liệu nội bộ - Internal Document Chatbot (Laravel + OpenAI + Qdrant)

Hệ thống chatbot nội bộ sử dụng tài liệu công ty để trả lời câu hỏi. Chatbot **chỉ dựa trên tài liệu đã train**, không được phép sử dụng kiến thức bên ngoài.

---

## 🧩 Công nghệ sử dụng

| Thành phần         | Công nghệ                              |
|--------------------|-----------------------------------------|
| Backend            | Laravel 10                              |
| Trí tuệ nhân tạo   | OpenAI GPT-3.5 (chat), OpenAI Embeddings |
| Vector Database    | Qdrant                                  |
| Trích xuất PDF     | smalot/pdfparser                        |
| Trích xuất Word    | phpoffice/phpword                       |
| Giao tiếp HTTP     | guzzlehttp/guzzle                      |
| Lưu log            | Eloquent (MySQL / SQLite)              |
| Web Server         | Nginx (chạy trong Docker)              |
| Quản lý môi trường | Docker + Docker Compose                |


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

## 🚀 Hướng dẫn chạy source

### 1. Chuẩn bị
- Tạo file `.env` (có thể sao chép từ `.env.example`)

### 2. Build và chạy container Docker
```bash
docker-compose up -d --build
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
```
### 3. Import file json API Postman có sẵn trong source
- Chatbot\chatbot_qdrant.postman_collection.json

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

# 📚 Internal Document Chatbot (Laravel + OpenAI + Qdrant)

📌 *Vietnamese version is above. This is the English version.*

---

## 🧩 Technologies Used

| Component               | Technology                               |
| ----------------------- | ---------------------------------------- |
| Backend                 | Laravel 10                               |
| Artificial Intelligence | OpenAI GPT-3.5 (chat), OpenAI Embeddings |
| Vector Database         | Qdrant                                   |
| PDF Extraction          | smalot/pdfparser                         |
| Word Extraction         | phpoffice/phpword                        |
| HTTP Client             | guzzlehttp/guzzle                        |
| Logging                 | Eloquent (MySQL / SQLite)                |
| Web Server              | Nginx (running in Docker)                |
| Environment Management  | Docker + Docker Compose                  |

---

### 🔹 Why Qdrant over MySQL?

| Feature                                | Qdrant (Vector DB)                | MySQL (Traditional RDBMS)               |
| -------------------------------------- | --------------------------------- | --------------------------------------- |
| **Store numerical vector (embedding)** | ✅ Purpose-built                   | ⚠️ Stored as JSON or TEXT (not optimal) |
| **Semantic similarity search**         | ✅ Built-in cosine / dot product   | ❌ Not supported, must code manually     |
| **Top-k nearest neighbors (ANN)**      | ✅ Fast with HNSW / IVF structures | ❌ Requires full data load to compare    |
| **Scale to millions of vectors**       | ✅ Efficient and scalable          | ❌ Slow and heavy (TEXT/JSON data)       |
| **Vector search API support**          | ✅ Has RESTful / GRPC              | ❌ None                                  |
| **Metadata support**                   | ✅ Attach text, ID, file, etc.     | ⚠️ Available but not linked with vector |
| **AI / Chatbot use case**              | ✅ Standard in RAG, AI search      | ❌ Not suitable                          |

---

### ⚡ Embedding Performance Comparison

| Criteria                                 | Qdrant (Vector DB)                | MySQL (embedding as JSON)             |
| ---------------------------------------- | --------------------------------- | ------------------------------------- |
| **Optimized for vector search**          | ✅ Yes (ANN index, HNSW, IVF...)   | ❌ No                                  |
| **Top-k similarity search (cosine/dot)** | ✅ Few milliseconds                | ❌ Very slow if > 5000 rows            |
| **Indexing for vectors**                 | ✅ Built-in                        | ❌ None                                |
| **Large scale performance**              | ✅ Smooth with millions of vectors | ❌ Heavy queries when many rows        |
| **Real-time queries (chatbot)**          | ✅ Optimized for realtime          | ❌ Laggy if comparing embedding in PHP |

---

## 📦 Deployment & Environment

### Sample `.env` file:

```env
OPENAI_API_KEY=your_openai_key_here
QDRANT_HOST=http://localhost:6333
```

---

## 🚀 How to Run the Project

### 1. Preparation

* Copy `.env.example` to `.env` and update values

### 2. Build and start Docker containers

```bash
docker-compose up -d --build
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
```

### 3. Import Postman JSON file

* Located at: `Chatbot/chatbot_qdrant.postman_collection.json`

---

## 👨‍💻 Notes

* The bot performs best when the text is **evenly chunked**, avoid long segments (>200 words)
* HTML / URL documents should have **script, style, meta, and inline JS/CSS removed**
* Easy to **extend ChatLog by user** if login system is integrated
* Avoid asking questions **outside trained document scope** – bot will not answer accurately

---

## ✅ Key Features

* 🤖 Answer questions based on PDF, DOCX, CSV, HTML, URL content
* 📎 Extract, chunk, and embed content into Qdrant
* 🧠 Recognize similar questions for consistent answers
* 🛑 No hallucinations – clear error if content not found
* 💬 Uses OpenAI (GPT-3.5/4) to generate context-aware answers

---

## ⚙️ System Workflow

### 1. Training Documents

1. **Upload document** → stored in `storage/app/`
2. **Extract content** using `DocumentParser`
3. **Chunk the text** using `TextChunker`
4. **Create embeddings** for each chunk via OpenAI
5. **Store in Qdrant** with `document_id`, `chunk_index`, and text

### 2. Answering Questions

1. **Embed the question** using OpenAI
2. **Compare with previous questions** in `chat_logs`

   * If cosine distance < 0.04 → reuse old answer
3. **Search for closest chunks** in Qdrant
4. **If no matching chunk** → return 404 error
5. **Send context + question to OpenAI GPT**
6. **Store logs**: question, embedding, answer

---

## 🧱 Project Structure

| File / Class     | Purpose                                            |
| ---------------- | -------------------------------------------------- |
| `ChatController` | Handles Q\&A logic and context matching            |
| `DocumentParser` | Extracts content from PDF, DOCX, CSV, HTML, URL    |
| `TextChunker`    | Splits text into chunks for training               |
| `Embedder`       | Calls OpenAI to generate embedding vectors         |
| `QdrantService`  | Manages Qdrant: collections, inserts, searches     |
| `ChatLog`        | Model for storing question, answer, and embeddings |

---

## 🔄 API Endpoints

### 🧠 Main Chat Endpoint

* `POST /api/ask`
  Ask question → receive chatbot response
  **Body:** `{ "question": "..." }`
  **Response:** `{ "answer": "..." }`

---

### 📄 Document Training

* `GET /train/{id}` – Train document by ID
* `GET /train-url?url=https://...` – Train content from a web page (text only)

---

### 🛠 Debugging Utilities

* `GET /test-read/{id}` – Read file content
* `GET /test-chunk/{id}` – View chunk preview
* `GET /test-embed` – Test generating embedding
* `GET /create-collection` – Create new Qdrant collection
* `GET /reindex` – Re-index collection after updates
* `GET /debug-vectors/{id}` – Inspect stored vector

---

## 📌 Important Notes

* Bot **only answers based on trained documents**. No context → 404 or custom error message.
* Duplicate or similar questions → returns previously stored answers.
* HTML/URL documents **automatically remove JS, CSS, meta** to reduce noise.

---

## 🧠 Matching Previous Questions Logic

```php
foreach ($pastLogs as $log) {
 $pastVector = json_decode($log->embedding, true);
 $distance = cosineDistance($newEmbedding, $pastVector);
 if ($distance < 0.04) {
     return $log->answer;
 }
}
```

---

## 📘 Postman API Collection - `chatbot_qdrant`

APIs for uploading, extracting, chunking, embedding, training, and chatting with the AI.

---

### 🔹 Upload & Extract

#### `POST /api/upload`

* Upload document (PDF, DOCX, CSV, HTML)
* **Body:** `form-data`

  * `file`: Document file

#### `GET /test-read/{id}`

* Read extracted content by document ID

#### `GET /test-url?url=...`

* Extract text from any given URL

---

### 🔹 Chunk & Embed

#### `GET /test-chunk/{id}`

* Chunk document text into small segments

#### `GET /test-chunk-url?url=...`

* Chunk text from a given URL

#### `GET /test-embed`

* Test embedding API on a sample text

---

### 🔹 Train Data into Qdrant

#### `GET /create-collection`

* Create `doc_chunks` collection in Qdrant

#### `GET /train/{id}`

* Train document content into Qdrant

#### `GET /train-url?url=...`

* Train content from a URL into Qdrant

---

### 🔹 Ask Questions

#### `POST /api/chat`

* Ask a question and get AI-generated answer from trained content
* **Body:** `form-data`

  * `question`: Your question

---

### 🔹 Manage Qdrant Data

#### `GET /reindex`

* Rebuild Qdrant index

#### `GET http://localhost:6333/collections/doc_chunks`

* View collection metadata

#### `GET http://localhost:6333/collections/doc_chunks/points/{id}`

* View individual vector by ID

#### `POST http://localhost:6333/collections/doc_chunks/points/scroll`

* Scroll through all points in collection

#### `DELETE http://localhost:6333/collections/doc_chunks`

* ⚠️ **Delete entire collection** from Qdrant

---

## 📌 Final Notes

* Ensure Docker containers (`Laravel`, `Qdrant`, `MySQL`) are running before using API
* Text is chunked to \~200 words before embedding
* The chatbot strictly answers based on trained documents only
