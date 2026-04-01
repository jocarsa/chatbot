<?php

/* =========================================
   CONFIG SMTP
========================================= */
define('SMTP_HOST', 'smtp.ionos.es');
define('SMTP_PORT', 587);
define('SMTP_USER', 'notificaciones@institutotame.es');
define('SMTP_PASS', 'TAMEDam123$');
define('SMTP_FROM_EMAIL', 'notificaciones@institutotame.es');
define('SMTP_FROM_NAME', 'Jocarsa Chatbot');
define('SMTP_SECURE', 'tls'); // 'tls', 'ssl' o ''

$dbfile = __DIR__ . '/admin.sqlite';
$debugDir = __DIR__ . '/debug_ai_logs';

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!is_array($data)) {
	header("Content-Type: text/plain; charset=utf-8");
	http_response_code(400);
	exit("JSON no válido");
}

/* =========================================
   PREPARAR CARPETA DE LOGS
========================================= */
if (!is_dir($debugDir)) {
	@mkdir($debugDir, 0775, true);
}

$accion = $data["accion"] ?? "";

/* =========================================
   CONEXIÓN SQLITE
========================================= */
try {
	$db = new PDO('sqlite:' . $dbfile);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$db->exec("
		CREATE TABLE IF NOT EXISTS usuarios (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			nombre TEXT NOT NULL,
			apellidos TEXT NOT NULL,
			email TEXT NOT NULL,
			telefono TEXT NOT NULL,
			curso_matriculado TEXT NOT NULL,
			creado_en TEXT DEFAULT CURRENT_TIMESTAMP
		)
	");

	$db->exec("
		CREATE TABLE IF NOT EXISTS chatbot_qa (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			pregunta TEXT NOT NULL,
			respuesta TEXT NOT NULL,
			creado_en TEXT DEFAULT CURRENT_TIMESTAMP
		)
	");

	$db->exec("
		CREATE TABLE IF NOT EXISTS ifttt_acciones (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			resumen_if TEXT NOT NULL,
			destinatario_email TEXT NOT NULL,
			asunto TEXT NOT NULL,
			creado_en TEXT DEFAULT CURRENT_TIMESTAMP
		)
	");

	$db->exec("
		CREATE TABLE IF NOT EXISTS conversaciones (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			telefono TEXT NOT NULL,
			rol TEXT NOT NULL,
			mensaje TEXT NOT NULL,
			creado_en TEXT DEFAULT CURRENT_TIMESTAMP
		)
	");

	$db->exec("
		CREATE TABLE IF NOT EXISTS ifttt_envios (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			ifttt_id INTEGER NOT NULL,
			telefono TEXT NOT NULL,
			resumen_hash TEXT NOT NULL,
			resumen_texto TEXT NOT NULL,
			destinatario_email TEXT NOT NULL,
			asunto TEXT NOT NULL,
			enviado_ok INTEGER NOT NULL DEFAULT 0,
			detalle_envio TEXT DEFAULT '',
			creado_en TEXT DEFAULT CURRENT_TIMESTAMP
		)
	");

	$db->exec("
		CREATE UNIQUE INDEX IF NOT EXISTS idx_ifttt_envio_unico
		ON ifttt_envios (ifttt_id, telefono, resumen_hash)
	");
} catch (Exception $e) {
	if ($accion === "identificar") {
		header("Content-Type: application/json; charset=utf-8");
		echo json_encode([
			"existe" => false,
			"error" => "No se pudo conectar a SQLite"
		], JSON_UNESCAPED_UNICODE);
		exit;
	} else {
		header("Content-Type: text/plain; charset=utf-8");
		exit("Error al conectar con SQLite");
	}
}

/* =========================================
   FUNCIONES AUXILIARES
========================================= */
function normalizarTexto($texto) {
	$texto = trim((string)$texto);
	$texto = mb_strtolower($texto, 'UTF-8');

	$reemplazos = [
		'á'=>'a','à'=>'a','ä'=>'a','â'=>'a',
		'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
		'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
		'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o',
		'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
		'ñ'=>'n'
	];
	$texto = strtr($texto, $reemplazos);

	$texto = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto);
	$texto = preg_replace('/\s+/u', ' ', $texto);

	return trim($texto);
}

function buscarUsuarioPorTelefono($db, $telefono) {
	$stmt = $db->prepare("
		SELECT id, nombre, apellidos, email, telefono, curso_matriculado
		FROM usuarios
		WHERE telefono = :telefono
		LIMIT 1
	");
	$stmt->execute([
		":telefono" => $telefono
	]);
	return $stmt->fetch(PDO::FETCH_ASSOC);
}

function recortarTexto($texto, $max = 4000) {
	$texto = (string)$texto;
	if (mb_strlen($texto, 'UTF-8') <= $max) {
		return $texto;
	}
	return mb_substr($texto, 0, $max, 'UTF-8') . "\n...[recortado]...";
}

function limpiarNombreArchivo($texto) {
	$texto = normalizarTexto($texto);
	$texto = preg_replace('/[^a-z0-9]+/i', '_', $texto);
	$texto = trim($texto, '_');
	if ($texto === '') {
		$texto = 'consulta';
	}
	return substr($texto, 0, 80);
}

function escribirDebugIA($debugDir, $info) {
	$fecha = date('Y-m-d_H-i-s');
	$micro = substr((string)microtime(true), -6);
	$slug = limpiarNombreArchivo($info['pregunta_original'] ?? 'consulta');
	$archivo = $debugDir . '/' . $fecha . '_' . $micro . '_' . $slug . '.txt';

	$txt = "";
	$txt .= "========================================\n";
	$txt .= "DEBUG IA REMOTA\n";
	$txt .= "========================================\n";
	$txt .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
	$txt .= "Accion: " . ($info['accion'] ?? '') . "\n";
	$txt .= "Telefono: " . ($info['telefono'] ?? '') . "\n";
	$txt .= "Usuario encontrado: " . (!empty($info['usuario_encontrado']) ? 'SI' : 'NO') . "\n";
	$txt .= "Fuente respuesta final: " . ($info['fuente'] ?? '') . "\n";
	$txt .= "URL remota: " . ($info['url_remota'] ?? '') . "\n";
	$txt .= "HTTP code: " . ($info['http_code'] ?? '') . "\n";
	$txt .= "Error cURL: " . ($info['curl_error'] ?? '') . "\n";
	$txt .= "Resumen conversación: " . ($info['resumen_conversacion'] ?? '') . "\n";
	$txt .= "IFTTT activado: " . (!empty($info['ifttt_activado']) ? 'SI' : 'NO') . "\n";
	$txt .= "IFTTT mensaje frontend: " . ($info['ifttt_mensaje_front'] ?? '') . "\n";
	$txt .= "\n";

	$txt .= "----------------------------------------\n";
	$txt .= "PREGUNTA ORIGINAL\n";
	$txt .= "----------------------------------------\n";
	$txt .= ($info['pregunta_original'] ?? '') . "\n\n";

	$txt .= "----------------------------------------\n";
	$txt .= "MEJORES COINCIDENCIAS LOCALES\n";
	$txt .= "----------------------------------------\n";
	if (!empty($info['mejores']) && is_array($info['mejores'])) {
		foreach ($info['mejores'] as $i => $item) {
			$n = $i + 1;
			$txt .= "Coincidencia #" . $n . "\n";
			$txt .= "ID: " . ($item['id'] ?? '') . "\n";
			$txt .= "Score: " . ($item['score'] ?? '') . "\n";
			$txt .= "Pregunta BD: " . ($item['pregunta'] ?? '') . "\n";
			$txt .= "Respuesta BD:\n" . ($item['respuesta'] ?? '') . "\n";
			$txt .= "\n";
		}
	} else {
		$txt .= "Sin coincidencias locales.\n\n";
	}

	$txt .= "----------------------------------------\n";
	$txt .= "PROMPT ENVIADO A LA IA REMOTA\n";
	$txt .= "----------------------------------------\n";
	$txt .= ($info['prompt'] ?? '') . "\n\n";

	$txt .= "----------------------------------------\n";
	$txt .= "RESPUESTA REMOTA\n";
	$txt .= "----------------------------------------\n";
	$txt .= ($info['respuesta_remota'] ?? '') . "\n\n";

	$txt .= "----------------------------------------\n";
	$txt .= "RESPUESTA FINAL DEVUELTA AL FRONT\n";
	$txt .= "----------------------------------------\n";
	$txt .= ($info['respuesta_final'] ?? '') . "\n\n";

	$txt .= "----------------------------------------\n";
	$txt .= "DETALLE IFTTT\n";
	$txt .= "----------------------------------------\n";
	if (!empty($info['ifttt_detalle']) && is_array($info['ifttt_detalle'])) {
		foreach ($info['ifttt_detalle'] as $k => $v) {
			if (is_array($v)) {
				$txt .= strtoupper((string)$k) . ":\n";

				$contador = 1;
				foreach ($v as $i => $fila) {
					$indice = is_scalar($i) ? (string)$i : '[indice]';

					if (is_array($fila)) {
						$txt .= "  #" . $contador . " [" . $indice . "]: " . json_encode($fila, JSON_UNESCAPED_UNICODE) . "\n";
					} else {
						$txt .= "  #" . $contador . " [" . $indice . "]: " . (string)$fila . "\n";
					}
					$contador++;
				}
				$txt .= "\n";
			} else {
				$txt .= (string)$k . ": " . (string)$v . "\n";
			}
		}
	} else {
		$txt .= "Sin detalle IFTTT.\n";
	}

	@file_put_contents($archivo, $txt);
	return $archivo;
}

function calcularScoreSimilitud($textoA, $textoB) {
	$a = normalizarTexto($textoA);
	$b = normalizarTexto($textoB);

	if ($a === '' || $b === '') {
		return 0;
	}

	$score = 0;

	if ($a === $b) {
		$score += 1000;
	}

	if (strpos($b, $a) !== false) {
		$score += 300;
	}
	if (strpos($a, $b) !== false) {
		$score += 250;
	}

	similar_text($a, $b, $porcentaje);
	$score += $porcentaje;

	$palabrasA = array_unique(array_filter(explode(' ', $a)));
	$palabrasB = array_unique(array_filter(explode(' ', $b)));

	if ($palabrasA && $palabrasB) {
		$comunes = array_intersect($palabrasA, $palabrasB);
		$score += count($comunes) * 12;
	}

	return $score;
}

/* =========================================
   CONVERSACIONES
========================================= */
function guardarMensajeConversacion($db, $telefono, $rol, $mensaje) {
	$telefono = trim((string)$telefono);
	$rol = trim((string)$rol);
	$mensaje = trim((string)$mensaje);

	if ($telefono === '' || $rol === '' || $mensaje === '') {
		return false;
	}

	$stmt = $db->prepare("
		INSERT INTO conversaciones (telefono, rol, mensaje)
		VALUES (:telefono, :rol, :mensaje)
	");
	return $stmt->execute([
		':telefono' => $telefono,
		':rol' => $rol,
		':mensaje' => $mensaje
	]);
}

function obtenerConversacionPorTelefono($db, $telefono, $limite = 30) {
	$stmt = $db->prepare("
		SELECT id, telefono, rol, mensaje, creado_en
		FROM conversaciones
		WHERE telefono = :telefono
		ORDER BY id ASC
		LIMIT :limite
	");
	$stmt->bindValue(':telefono', $telefono, PDO::PARAM_STR);
	$stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
	$stmt->execute();
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatearConversacionParaResumen($historial) {
	if (!$historial) {
		return '';
	}

	$txt = "";
	foreach ($historial as $fila) {
		$rol = ($fila['rol'] === 'assistant') ? 'Asistente' : 'Usuario';
		$txt .= $rol . ": " . trim((string)$fila['mensaje']) . "\n";
	}
	return trim($txt);
}

function resumirConversacionLocal($historial) {
	$mensajesUsuario = [];

	foreach ($historial as $fila) {
		if (($fila['rol'] ?? '') === 'user') {
			$mensajesUsuario[] = trim((string)$fila['mensaje']);
		}
	}

	if (!$mensajesUsuario) {
		return '';
	}

	$ultimos = array_slice($mensajesUsuario, -3);
	return implode(' | ', $ultimos);
}

/* =========================================
   BUSCAR MEJORES Q&A EN SQLITE
========================================= */
function buscarMejoresRespuestasLocales($db, $pregunta, $limite = 3) {
	$preguntaNorm = normalizarTexto($pregunta);

	$stmt = $db->query("SELECT id, pregunta, respuesta FROM chatbot_qa ORDER BY id DESC");
	$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$resultados = [];

	foreach ($filas as $fila) {
		$preguntaBDNorm = normalizarTexto($fila['pregunta']);
		$score = calcularScoreSimilitud($preguntaNorm, $preguntaBDNorm);

		$resultados[] = [
			'id' => $fila['id'],
			'pregunta' => $fila['pregunta'],
			'respuesta' => $fila['respuesta'],
			'score' => $score
		];
	}

	usort($resultados, function($a, $b) {
		return $b['score'] <=> $a['score'];
	});

	return array_slice($resultados, 0, $limite);
}

/* =========================================
   LLAMAR A LA IA REMOTA
========================================= */
function preguntarIAremota($prompt) {
	$url = "https://XXX/chat/?q=" . urlencode($prompt);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

	$response = curl_exec($ch);
	$curlError = '';

	if (curl_errno($ch)) {
		$curlError = curl_error($ch);
	}

	$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	return [
		'url' => $url,
		'response' => $response === false ? '' : trim((string)$response),
		'http_code' => (int)$http,
		'curl_error' => $curlError
	];
}

function resumirConversacion($telefono, $historial) {
	$resumen = resumirConversacionLocal($historial);

	return [
		'resumen' => $resumen,
		'prompt' => '',
		'url' => '',
		'http_code' => 0,
		'curl_error' => '',
		'respuesta_remota' => ''
	];
}

/* =========================================
   IFTTT
========================================= */
function buscarMejoresIfttt($db, $resumen, $limite = 3) {
	$stmt = $db->query("
		SELECT id, resumen_if, destinatario_email, asunto, creado_en
		FROM ifttt_acciones
		ORDER BY id DESC
	");
	$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$resultados = [];

	foreach ($filas as $fila) {
		$score = calcularScoreSimilitud($resumen, $fila['resumen_if']);

		$resultados[] = [
			'id' => $fila['id'],
			'resumen_if' => $fila['resumen_if'],
			'destinatario_email' => $fila['destinatario_email'],
			'asunto' => $fila['asunto'],
			'creado_en' => $fila['creado_en'],
			'score' => $score
		];
	}

	usort($resultados, function($a, $b) {
		return $b['score'] <=> $a['score'];
	});

	return array_slice($resultados, 0, $limite);
}

function yaSeEnvioEsteIfttt($db, $iftttId, $telefono, $resumenHash) {
	$stmt = $db->prepare("
		SELECT id
		FROM ifttt_envios
		WHERE ifttt_id = :ifttt_id
		  AND telefono = :telefono
		  AND resumen_hash = :resumen_hash
		LIMIT 1
	");
	$stmt->execute([
		':ifttt_id' => $iftttId,
		':telefono' => $telefono,
		':resumen_hash' => $resumenHash
	]);
	return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function registrarEnvioIfttt($db, $iftttId, $telefono, $resumenHash, $resumenTexto, $destinatario, $asunto, $ok, $detalle) {
	$stmt = $db->prepare("
		INSERT OR IGNORE INTO ifttt_envios (
			ifttt_id,
			telefono,
			resumen_hash,
			resumen_texto,
			destinatario_email,
			asunto,
			enviado_ok,
			detalle_envio
		) VALUES (
			:ifttt_id,
			:telefono,
			:resumen_hash,
			:resumen_texto,
			:destinatario_email,
			:asunto,
			:enviado_ok,
			:detalle_envio
		)
	");
	return $stmt->execute([
		':ifttt_id' => $iftttId,
		':telefono' => $telefono,
		':resumen_hash' => $resumenHash,
		':resumen_texto' => $resumenTexto,
		':destinatario_email' => $destinatario,
		':asunto' => $asunto,
		':enviado_ok' => $ok ? 1 : 0,
		':detalle_envio' => $detalle
	]);
}

/* =========================================
   SMTP SOCKET
========================================= */
function smtpLeerRespuesta($fp) {
	$respuesta = '';
	while (!feof($fp)) {
		$linea = fgets($fp, 515);
		if ($linea === false) {
			break;
		}
		$respuesta .= $linea;
		if (preg_match('/^\d{3}\s/', $linea)) {
			break;
		}
	}
	return $respuesta;
}

function smtpEsperarCodigo($fp, $codigosValidos = [], &$debug = null) {
	$respuesta = smtpLeerRespuesta($fp);
	if (is_array($debug)) {
		$debug[] = "S: " . trim($respuesta);
	}
	$codigo = (int)substr(trim($respuesta), 0, 3);

	if (!in_array($codigo, $codigosValidos, true)) {
		throw new Exception("Esperado SMTP " . implode(',', $codigosValidos) . " pero llegó: " . trim($respuesta));
	}

	return $respuesta;
}

function smtpEnviarLinea($fp, $linea, &$debug = null) {
	if (is_array($debug)) {
		$visible = $linea;
		if (stripos($linea, 'AUTH LOGIN') === false && preg_match('/^[A-Za-z0-9+\/=]+$/', $linea)) {
			$visible = '[BASE64_HIDDEN]';
		}
		$debug[] = "C: " . $visible;
	}
	fwrite($fp, $linea . "\r\n");
}

function smtpEscaparCabecera($texto) {
	$texto = str_replace(["\r", "\n"], ' ', (string)$texto);
	return trim($texto);
}

function smtpNormalizarCuerpo($texto) {
	$texto = str_replace(["\r\n", "\r"], "\n", (string)$texto);
	$lineas = explode("\n", $texto);

	foreach ($lineas as &$linea) {
		if (isset($linea[0]) && $linea[0] === '.') {
			$linea = '.' . $linea;
		}
	}

	return implode("\r\n", $lineas);
}

function enviarCorreoIfttt($destinatario, $asunto, $cuerpo, $replyTo = '') {
	$debug = [];

	$host = SMTP_HOST;
	$port = (int)SMTP_PORT;
	$user = SMTP_USER;
	$pass = SMTP_PASS;
	$fromEmail = SMTP_FROM_EMAIL;
	$fromName = SMTP_FROM_NAME;
	$secure = SMTP_SECURE;

	$timeout = 30;
	$errno = 0;
	$errstr = '';

	$transporte = ($secure === 'ssl' ? 'ssl://' : '') . $host;
	$fp = @fsockopen($transporte, $port, $errno, $errstr, $timeout);

	if (!$fp) {
		return [
			'ok' => 0,
			'detalle' => "No se pudo conectar al servidor SMTP: $errstr ($errno)"
		];
	}

	stream_set_timeout($fp, $timeout);

	try {
		smtpEsperarCodigo($fp, [220], $debug);

		$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';

		smtpEnviarLinea($fp, "EHLO " . $hostname, $debug);
		smtpEsperarCodigo($fp, [250], $debug);

		if ($secure === 'tls') {
			smtpEnviarLinea($fp, "STARTTLS", $debug);
			smtpEsperarCodigo($fp, [220], $debug);

			if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
				throw new Exception("No se pudo activar TLS");
			}

			smtpEnviarLinea($fp, "EHLO " . $hostname, $debug);
			smtpEsperarCodigo($fp, [250], $debug);
		}

		smtpEnviarLinea($fp, "AUTH LOGIN", $debug);
		smtpEsperarCodigo($fp, [334], $debug);

		smtpEnviarLinea($fp, base64_encode($user), $debug);
		smtpEsperarCodigo($fp, [334], $debug);

		smtpEnviarLinea($fp, base64_encode($pass), $debug);
		smtpEsperarCodigo($fp, [235], $debug);

		smtpEnviarLinea($fp, "MAIL FROM:<" . $fromEmail . ">", $debug);
		smtpEsperarCodigo($fp, [250], $debug);

		smtpEnviarLinea($fp, "RCPT TO:<" . trim($destinatario) . ">", $debug);
		smtpEsperarCodigo($fp, [250, 251], $debug);

		smtpEnviarLinea($fp, "DATA", $debug);
		smtpEsperarCodigo($fp, [354], $debug);

		$headers = [];
		$headers[] = "Date: " . date('r');
		$headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <" . $fromEmail . ">";
		$headers[] = "To: <" . trim($destinatario) . ">";
		if (trim($replyTo) !== '') {
			$headers[] = "Reply-To: " . trim($replyTo);
		}
		$headers[] = "Subject: =?UTF-8?B?" . base64_encode(smtpEscaparCabecera($asunto)) . "?=";
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/plain; charset=UTF-8";
		$headers[] = "Content-Transfer-Encoding: 8bit";

		$mensaje = implode("\r\n", $headers) . "\r\n\r\n" . smtpNormalizarCuerpo($cuerpo);

		$debug[] = "C: [DATA_BODY_SENT]";
		fwrite($fp, $mensaje . "\r\n.\r\n");
		smtpEsperarCodigo($fp, [250], $debug);

		smtpEnviarLinea($fp, "QUIT", $debug);
		@fclose($fp);

		return [
			'ok' => 1,
			'detalle' => "SMTP enviado correctamente\n" . implode("\n", $debug)
		];
	} catch (Exception $e) {
		@fclose($fp);
		return [
			'ok' => 0,
			'detalle' => "Error SMTP: " . $e->getMessage() . "\n" . implode("\n", $debug)
		];
	}
}

function obtenerUltimoMensajeUsuario($historial) {
	for ($i = count($historial) - 1; $i >= 0; $i--) {
		if (($historial[$i]['rol'] ?? '') === 'user') {
			return trim((string)$historial[$i]['mensaje']);
		}
	}
	return '';
}

function obtenerTextoPlanoConversacion($historial, $limite = 10) {
	$ultimos = array_slice($historial, -$limite);
	$partes = [];

	foreach ($ultimos as $fila) {
		$partes[] = trim((string)$fila['mensaje']);
	}

	return trim(implode(' ', $partes));
}

function buscarMejoresIftttMulti($db, $textos, $limite = 3) {
	$stmt = $db->query("
		SELECT id, resumen_if, destinatario_email, asunto, creado_en
		FROM ifttt_acciones
		ORDER BY id DESC
	");
	$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$resultados = [];

	foreach ($filas as $fila) {
		$mejorScore = 0;
		$mejorFuente = '';

		foreach ($textos as $nombreFuente => $texto) {
			$texto = trim((string)$texto);
			if ($texto === '') {
				continue;
			}

			$score = calcularScoreSimilitud($texto, $fila['resumen_if']);

			if ($score > $mejorScore) {
				$mejorScore = $score;
				$mejorFuente = $nombreFuente;
			}
		}

		$resultados[] = [
			'id' => $fila['id'],
			'resumen_if' => $fila['resumen_if'],
			'destinatario_email' => $fila['destinatario_email'],
			'asunto' => $fila['asunto'],
			'creado_en' => $fila['creado_en'],
			'score' => $mejorScore,
			'fuente_match' => $mejorFuente
		];
	}

	usort($resultados, function($a, $b) {
		return $b['score'] <=> $a['score'];
	});

	return array_slice($resultados, 0, $limite);
}

function procesarIfttt($db, $telefono, $usuario, $resumen, $historial) {
	$resultado = [
		'activado' => false,
		'mensaje_front' => '',
		'detalle' => [
			'resumen' => $resumen,
			'mejores_ifttt' => []
		]
	];

	$ultimoMensajeUsuario = obtenerUltimoMensajeUsuario($historial);
	$textoConversacion = obtenerTextoPlanoConversacion($historial, 10);

	$textosComparacion = [
		'ultimo_mensaje_usuario' => $ultimoMensajeUsuario,
		'texto_conversacion' => $textoConversacion
	];

	$mejores = buscarMejoresIftttMulti($db, $textosComparacion, 3);
	$resultado['detalle']['mejores_ifttt'] = $mejores;
	$resultado['detalle']['textos_comparacion'] = $textosComparacion;

	$mejor = $mejores[0] ?? null;
	if (!$mejor) {
		$resultado['detalle']['estado'] = 'sin_reglas';
		return $resultado;
	}

	$umbral = 70;

	if ((float)$mejor['score'] < $umbral) {
		$resultado['detalle']['estado'] = 'score_bajo';
		$resultado['detalle']['score_mejor'] = $mejor['score'];
		return $resultado;
	}

	$textoBaseHash = $textosComparacion[$mejor['fuente_match']] ?? $ultimoMensajeUsuario;
	$resumenHash = sha1(normalizarTexto($textoBaseHash));

	if (yaSeEnvioEsteIfttt($db, (int)$mejor['id'], $telefono, $resumenHash)) {
		$resultado['activado'] = true;
		$resultado['mensaje_front'] = "Correo ya enviado anteriormente a " . $mejor['destinatario_email'] . ".";
		$resultado['detalle']['estado'] = 'duplicado_no_reenviado';
		return $resultado;
	}

	$nombreCompleto = '';
	$emailUsuario = '';
	$curso = '';

	if ($usuario) {
		$nombreCompleto = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
		$emailUsuario = trim((string)($usuario['email'] ?? ''));
		$curso = trim((string)($usuario['curso_matriculado'] ?? ''));
	}

	$cuerpo = "";
	$cuerpo .= "Se ha activado una regla IFTTT del chatbot.\n\n";
	$cuerpo .= "Regla coincidente:\n";
	$cuerpo .= $mejor['resumen_if'] . "\n\n";
	$cuerpo .= "Fuente del match: " . $mejor['fuente_match'] . "\n";
	$cuerpo .= "Score: " . $mejor['score'] . "\n\n";
	$cuerpo .= "Resumen local:\n" . $resumen . "\n\n";
	$cuerpo .= "Último mensaje del usuario:\n" . $ultimoMensajeUsuario . "\n\n";
	$cuerpo .= "Texto reciente de conversación:\n" . $textoConversacion . "\n\n";
	$cuerpo .= "Datos del usuario:\n";
	$cuerpo .= "- Nombre: " . ($nombreCompleto !== '' ? $nombreCompleto : '[no disponible]') . "\n";
	$cuerpo .= "- Email: " . ($emailUsuario !== '' ? $emailUsuario : '[no disponible]') . "\n";
	$cuerpo .= "- Teléfono: " . ($telefono !== '' ? $telefono : '[no disponible]') . "\n";
	$cuerpo .= "- Curso matriculado: " . ($curso !== '' ? $curso : '[no disponible]') . "\n\n";
	$cuerpo .= "Conversación reciente:\n";

	$ultimos = array_slice($historial, -10);
	foreach ($ultimos as $fila) {
		$rol = ($fila['rol'] === 'assistant') ? 'Asistente' : 'Usuario';
		$cuerpo .= $rol . ": " . trim((string)$fila['mensaje']) . "\n";
	}

	$envio = enviarCorreoIfttt(
		$mejor['destinatario_email'],
		$mejor['asunto'],
		$cuerpo,
		$emailUsuario
	);

	registrarEnvioIfttt(
		$db,
		(int)$mejor['id'],
		$telefono,
		$resumenHash,
		$textoBaseHash,
		$mejor['destinatario_email'],
		$mejor['asunto'],
		!empty($envio['ok']),
		(string)$envio['detalle']
	);

	$resultado['activado'] = true;

	if (!empty($envio['ok'])) {
		$resultado['mensaje_front'] = "Correo enviado a " . $mejor['destinatario_email'] . ".";
		$resultado['detalle']['estado'] = 'enviado';
	} else {
		$resultado['mensaje_front'] = "No se pudo enviar el correo a " . $mejor['destinatario_email'] . ".";
		$resultado['detalle']['estado'] = 'error_envio';
	}

	$resultado['detalle']['accion_elegida'] = $mejor;
	$resultado['detalle']['envio'] = $envio;

	return $resultado;
}

/* =========================================
   ACCIÓN: IDENTIFICAR USUARIO
========================================= */
if ($accion === "identificar") {
	header("Content-Type: application/json; charset=utf-8");

	$telefono = trim($data["telefono"] ?? "");

	if ($telefono === "") {
		echo json_encode([
			"existe" => false,
			"error" => "No se recibió teléfono"
		], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$usuario = buscarUsuarioPorTelefono($db, $telefono);

	if ($usuario) {
		echo json_encode([
			"existe" => true,
			"id" => $usuario["id"],
			"nombre" => $usuario["nombre"],
			"apellidos" => $usuario["apellidos"],
			"email" => $usuario["email"],
			"telefono" => $usuario["telefono"],
			"curso_matriculado" => $usuario["curso_matriculado"]
		], JSON_UNESCAPED_UNICODE);
	} else {
		echo json_encode([
			"existe" => false,
			"telefono" => $telefono
		], JSON_UNESCAPED_UNICODE);
	}
	exit;
}

/* =========================================
   ACCIÓN: CHAT
========================================= */
if ($accion === "chat") {
	header("Content-Type: text/plain; charset=utf-8");

	$pregunta = trim($data["mensaje"] ?? "");
	$telefono = trim($data["telefono"] ?? "");

	if ($pregunta === "") {
		http_response_code(400);
		exit("Mensaje vacío");
	}

	$usuario = null;
	if ($telefono !== "") {
		$usuario = buscarUsuarioPorTelefono($db, $telefono);
	}

	$mejores = buscarMejoresRespuestasLocales($db, $pregunta, 3);
	$mejor = $mejores[0] ?? null;

	$contextoQA = "";
	if (!empty($mejores)) {
		$contextoQA .= "Base local de preguntas y respuestas:\n";
		foreach ($mejores as $i => $item) {
			$n = $i + 1;
			$contextoQA .= "[$n] Pregunta: " . $item['pregunta'] . "\n";
			$contextoQA .= "[$n] Respuesta: " . $item['respuesta'] . "\n\n";
		}
	}

	$contextoUsuario = "";
	if ($usuario) {
		$contextoUsuario .= "Datos del usuario identificados por teléfono:\n";
		$contextoUsuario .= "- Nombre: " . $usuario['nombre'] . " " . $usuario['apellidos'] . "\n";
		$contextoUsuario .= "- Email: " . $usuario['email'] . "\n";
		$contextoUsuario .= "- Teléfono: " . $usuario['telefono'] . "\n";
		$contextoUsuario .= "- Curso matriculado: " . $usuario['curso_matriculado'] . "\n\n";
	}

	$prompt =
		"Eres un asistente de atención al alumno.\n" .
		"Responde en español, de forma clara y breve.\n" .
		"Si la base local contiene información útil, dale prioridad.\n" .
		"No inventes datos que no aparezcan ni en la base local ni en la pregunta.\n\n" .
		$contextoUsuario .
		$contextoQA .
		"Pregunta del usuario:\n" . $pregunta;

	$fuente = '';
	$respuestaFinal = '';
	$respuestaRemota = '';
	$httpCode = '';
	$curlError = '';
	$urlRemota = '';

	if ($telefono !== '') {
		guardarMensajeConversacion($db, $telefono, 'user', $pregunta);
	}

	if ($mejor && $mejor['score'] >= 180) {
		$fuente = 'sqlite_directa + debug_remoto';
		$respuestaFinal = $mejor['respuesta'];

		$remoto = preguntarIAremota($prompt);
		$respuestaRemota = $remoto['response'];
		$httpCode = $remoto['http_code'];
		$curlError = $remoto['curl_error'];
		$urlRemota = $remoto['url'];
	} else {
		$fuente = 'ia_remota';
		$remoto = preguntarIAremota($prompt);

		$respuestaRemota = $remoto['response'];
		$httpCode = $remoto['http_code'];
		$curlError = $remoto['curl_error'];
		$urlRemota = $remoto['url'];

		if ($curlError !== '') {
			$respuestaFinal = "Error cURL: " . $curlError;
		} elseif ((int)$httpCode >= 400) {
			$respuestaFinal = "Error remoto HTTP " . $httpCode;
		} else {
			$respuestaFinal = $respuestaRemota !== '' ? $respuestaRemota : "No se pudo obtener respuesta.";
		}
	}

	if ($telefono !== '') {
		guardarMensajeConversacion($db, $telefono, 'assistant', $respuestaFinal);
	}

	$resumenConversacion = '';
	$resumenPrompt = '';
	$resumenRemoto = '';
	$resumenHttp = '';
	$resumenCurlError = '';
	$iftttResultado = [
		'activado' => false,
		'mensaje_front' => '',
		'detalle' => []
	];

	if ($telefono !== '') {
		$historial = obtenerConversacionPorTelefono($db, $telefono, 50);

		$resumenConversacion = resumirConversacionLocal($historial);
		$resumenPrompt = '';
		$resumenRemoto = '';
		$resumenHttp = 0;
		$resumenCurlError = '';

		$iftttResultado = procesarIfttt($db, $telefono, $usuario, $resumenConversacion, $historial);
	}

	$respuestaFront = $respuestaFinal;
	if (!empty($iftttResultado['mensaje_front'])) {
		$respuestaFront .= "\n\n" . $iftttResultado['mensaje_front'];
	}

	escribirDebugIA($debugDir, [
		'accion' => 'chat',
		'telefono' => $telefono,
		'usuario_encontrado' => $usuario ? 1 : 0,
		'fuente' => $fuente,
		'url_remota' => $urlRemota,
		'http_code' => $httpCode,
		'curl_error' => $curlError,
		'pregunta_original' => $pregunta,
		'mejores' => $mejores,
		'prompt' => recortarTexto($prompt, 20000),
		'respuesta_remota' => recortarTexto($respuestaRemota, 20000),
		'respuesta_final' => recortarTexto($respuestaFront, 20000),
		'resumen_conversacion' => recortarTexto($resumenConversacion, 2000),
		'ifttt_activado' => !empty($iftttResultado['activado']) ? 1 : 0,
		'ifttt_mensaje_front' => $iftttResultado['mensaje_front'] ?? '',
		'ifttt_detalle' => array_merge(
			$iftttResultado['detalle'] ?? [],
			[
				'resumen_prompt' => recortarTexto($resumenPrompt, 12000),
				'resumen_respuesta_remota' => recortarTexto($resumenRemoto, 4000),
				'resumen_http_code' => $resumenHttp,
				'resumen_curl_error' => $resumenCurlError
			]
		)
	]);

	exit($respuestaFront);
}

/* =========================================
   ACCIÓN NO VÁLIDA
========================================= */
header("Content-Type: text/plain; charset=utf-8");
http_response_code(400);
echo "Acción no válida";
?>
