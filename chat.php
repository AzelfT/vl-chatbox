<?php
// chat.php
// (1) Nếu FE gọi khác domain, mở CORS (tùy nhu cầu, có thể comment 3 dòng dưới)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: application/json; charset=utf-8');


// Đọc JSON input an toàn
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '[]', true);
$messages = isset($input['messages']) && is_array($input['messages']) ? $input['messages'] : [];

// Ghép system prompt (đổi đúng ngữ cảnh showroom ô tô)
$system = [
  'role' => 'system',
  'content' =>
    "Bạn là trợ lý AI cho showroom ô tô VL AutoGallery. Trả lời ngắn gọn, thân thiện, rõ ràng; " .
    "ưu tiên thông tin xe, giá, khuyến mãi, lái thử. Không tư vấn y khoa. Ngôn ngữ: tiếng Việt."
];

// Fallback nếu người dùng chưa gửi gì
if (empty($messages)) {
  $messages = [['role' => 'user', 'content' => 'Xin chào!']];
}
array_unshift($messages, $system);

// ======= Gọi OpenAI (Chat Completions) =======
// Khuyến nghị: để API key trong biến môi trường OPENAI_API_KEY
$apiKey = getenv('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');
if (!$apiKey) {
  // TẠM: fallback key cứng (không khuyến nghị đưa production)
  $apiKey = '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';
}

$model  = 'gpt-3.5-turbo'; // đổi model nếu bạn có quyền model khác

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
    CURLOPT_TIMEOUT => 30,
    // Một số host cũ cần ép TLS 1.2
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
  ]);

  $res  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($res === false) {
  echo json_encode([
    'reply' => 'Không thể kết nối API: ' . $err,
    'debug' => [
      'code' => $code,
      'payload' => $payload,
    ]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

  $json = json_decode($res, true);

  // Bắt lỗi phổ biến hơn: key sai / model không có quyền / rate limit
  if ($code >= 400) {
    $msg = $json['error']['message'] ?? 'Máy chủ AI trả về lỗi (' . $code . ').';
    echo json_encode(['reply' => $msg], JSON_UNESCAPED_UNICODE); exit;
  }

  $reply = $json['choices'][0]['message']['content'] ?? null;
  if (!$reply) {
    echo json_encode(['reply' => 'Hiện không nhận được nội dung trả lời. Vui lòng thử lại.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['reply' => 'Đã xảy ra lỗi phía server: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
