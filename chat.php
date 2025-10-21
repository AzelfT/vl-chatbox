<?php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

header('Content-Type: application/json; charset=utf-8');

// ====== NHẬN DỮ LIỆU TỪ FRONTEND ======
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '[]', true);
$messages = isset($input['messages']) && is_array($input['messages']) ? $input['messages'] : [];

// Fallback nếu chỉ có "message"
if (empty($messages) && isset($input['message'])) {
  $messages = [['role' => 'user', 'content' => $input['message']]];
}

// ====== THÔNG ĐIỆP HỆ THỐNG (SYSTEM PROMPT) ======
$system = [
  'role' => 'system',
  'content' => "Bạn là trợ lý AI cho showroom ô tô VL AutoGallery. 
  Trả lời ngắn gọn, thân thiện, rõ ràng; ưu tiên thông tin xe, giá, khuyến mãi, lái thử. 
  Không tư vấn y khoa. Ngôn ngữ: tiếng Việt."
];

// Nếu chưa có message, tạo mặc định
if (empty($messages)) {
  $messages = [['role' => 'user', 'content' => 'Xin chào!']];
}
array_unshift($messages, $system);

// ====== LẤY API KEY TỪ BIẾN MÔI TRƯỜNG ======
$apiKey = getenv("OPENAI_API_KEY");

// Nếu server chưa có biến môi trường
if (!$apiKey) {
  echo json_encode([
    'reply' => '⚠️ Biến môi trường OPENAI_API_KEY chưa được thiết lập trên server Railway.'
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ====== GỌI API OPENAI ======
$model = 'gpt-4o-mini';

try {
  $payload = json_encode([
    'model' => $model,
    'messages' => $messages,
    'temperature' => 0.3,
    'max_tokens' => 400,
  ], JSON_UNESCAPED_UNICODE);

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: ' . 'Bearer ' . $apiKey,
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
  ]);

  $res = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false) {
    echo json_encode([
      'reply' => 'Không thể kết nối API: ' . $err,
      'debug' => ['code' => $code]
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $json = json_decode($res, true);

  if ($code >= 400) {
    $msg = $json['error']['message'] ?? "Máy chủ AI trả về lỗi ($code).";
    echo json_encode(['reply' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $reply = $json['choices'][0]['message']['content'] ?? 'Không có phản hồi.';
  echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode([
    'reply' => 'Lỗi server: ' . $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}
