<?php
/**
 * A minimal SMTP server for offline mail tests.
 *
 *   php tests/fixtures/fake_smtp.php 8025
 *
 * Speaks enough SMTP for the Athenaeum client: greeting, EHLO (with the
 * AUTH PLAIN LOGIN capability), AUTH PLAIN / AUTH LOGIN, MAIL FROM, RCPT TO,
 * DATA (dot-terminated) and QUIT. The full transcript plus the received message
 * are written to files in the system temp directory:
 *
 *   fake-smtp-transcript.txt   every line exchanged
 *   fake-smtp-message.txt      the payload of the last DATA block
 *
 * Environment:
 *   FAKE_SMTP_MODE=ok|reject_auth|reject_rcpt|error_data
 */

declare(strict_types=1);

$port = (int) ($argv[1] ?? 8025);
$mode = getenv('FAKE_SMTP_MODE') ?: 'ok';
$transcriptFile = sys_get_temp_dir() . '/fake-smtp-transcript.txt';
$messageFile = sys_get_temp_dir() . '/fake-smtp-message.txt';
@unlink($transcriptFile);
@unlink($messageFile);

$server = @stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "cannot listen on {$port}: {$errstr}\n");
    exit(1);
}
echo "fake SMTP listening on 127.0.0.1:{$port} (mode: {$mode})\n";

$transcript = static function (string $line) use ($transcriptFile): void {
    @file_put_contents($transcriptFile, $line . "\n", FILE_APPEND);
};

// Serve connections until the process is stopped, so a long test run can send
// several messages (this is a test fixture, not a daemon: Ctrl+C to stop it).
while (true) {
    $client = @stream_socket_accept($server, 5);
    if ($client === false) {
        continue;
    }
    stream_set_timeout($client, 10);
    $write = static function (string $line) use ($client, $transcript): void {
        fwrite($client, $line . "\r\n");
        $transcript('S: ' . $line);
    };

    $write('220 fake-smtp ready');

    $inData = false;
    $message = '';
    while (($line = fgets($client, 8192)) !== false) {
        $line = rtrim($line, "\r\n");
        if (!$inData) {
            $transcript('C: ' . (str_starts_with($line, 'AUTH') ? substr($line, 0, 16) . '…' : $line));
        }

        if ($inData) {
            if ($line === '.') {
                $inData = false;
                @file_put_contents($messageFile, $message);
                if ($mode === 'error_data') {
                    $write('554 message rejected by policy');
                } else {
                    $write('250 2.0.0 Ok: queued as FAKE123');
                }
                continue;
            }
            $message .= $line . "\n";
            continue;
        }

        $upper = strtoupper($line);
        if (str_starts_with($upper, 'EHLO') || str_starts_with($upper, 'HELO')) {
            $write('250-fake-smtp greets you');
            $write('250-SIZE 20480000');
            $write('250-AUTH PLAIN LOGIN');
            $write('250 8BITMIME');
            continue;
        }
        if (str_starts_with($upper, 'AUTH PLAIN')) {
            if ($mode === 'reject_auth') {
                $write('535 5.7.8 authentication credentials invalid');
            } else {
                $write('235 2.7.0 authentication successful');
            }
            continue;
        }
        if ($upper === 'AUTH LOGIN') {
            $write('334 VXNlcm5hbWU6');
            $user = fgets($client, 8192);
            $transcript('C: <username base64>');
            $write('334 UGFzc3dvcmQ6');
            $password = fgets($client, 8192);
            $transcript('C: <password base64>');
            if ($mode === 'reject_auth') {
                $write('535 5.7.8 authentication credentials invalid');
            } else {
                $write('235 2.7.0 authentication successful');
            }
            unset($user, $password);
            continue;
        }
        if (str_starts_with($upper, 'MAIL FROM')) {
            $write('250 2.1.0 sender ok');
            continue;
        }
        if (str_starts_with($upper, 'RCPT TO')) {
            if ($mode === 'reject_rcpt') {
                $write('550 5.1.1 no such user here');
            } else {
                $write('250 2.1.5 recipient ok');
            }
            continue;
        }
        if ($upper === 'DATA') {
            $write('354 end data with <CR><LF>.<CR><LF>');
            $inData = true;
            $message = '';
            continue;
        }
        if ($upper === 'QUIT') {
            $write('221 2.0.0 bye');
            break;
        }
        if ($upper === 'RSET') {
            $write('250 2.0.0 ok');
            continue;
        }
        $write('250 2.0.0 ok');
    }

    @fclose($client);
}

@fclose($server);
echo "fake SMTP finished\n";
