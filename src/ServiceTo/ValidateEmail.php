<?php
	namespace ServiceTo;

	class ValidateEmail {
		private $testaddress = "validateemail@service.to";

		function test($emailaddress) {
			$this->lookup($emailaddress);
		}
		function lookup($emailaddress) {
			list($user, $domain) = preg_split("/@/", trim($emailaddress));

			$mxhosts = array();
			$weight = array();
			if (getmxrr($domain, $mxhosts, $weight)) {
				// pick first one and check it.

				array_multisort($weight, $mxhosts);

				foreach ($mxhosts as $id => $mxhost) {
					if ($this->verify($emailaddress, $mxhost)) {
						return 1;
					}
				}
			}
			else {
				throw new ValidateEmailException("No MX records");
			}
		}

		function verify($emailaddress, $mxhost) {
			$validated = 0;

			$socket = stream_socket_client("tcp://" . $mxhost . ":25", $errno, $errstr, 30);
			stream_set_blocking($socket, true);

			$buffer = null;
			// server will say hi...
			$buffer .= fgets($socket, 2048);

			$response = trim($buffer, "\r\n.");
			$buffer = null;

			list($code, $message) = preg_split("/\s+/", $response, 2);
			if ($code == 220) {
				// say hello.
				$message = "EHLO ValidateEmail\r\n";
				fwrite($socket, $message);

				while($buf = fgets($socket)) {
					$buffer .= $buf;
					list($code, $message) = preg_split("/\s+/", $buf, 2);
					if ($code == "250") {
						break;
					}
				}
				$response = trim($buffer, "\r\n");
				$buffer = null;

				$lines = preg_split("/\n/", $response);
				$last = count($lines) - 1;
				list($code, $message) = preg_split("/\s+/", $lines[$last], 2);
				if ($code == 250) {
					// give them my from address
					$message = "MAIL FROM:<" . $this->testaddress . ">\r\n";
					fwrite($socket, $message);

					$buffer .= fgets($socket, 2048);

					$response = trim($buffer, "\r\n");
					$buffer = null;

					list($code, $message) = preg_split("/\s+/", $response, 2);

					if ($code == 250) {
						// give them the user's address.
						$message = "RCPT TO:<" . $emailaddress . ">\r\n";
						fwrite($socket, $message);

						$buffer .= fgets($socket, 2048);

						$response = trim($buffer, "\r\n");
						$buffer = null;

						list($code, $message) = preg_split("/\s+/", $response, 2);
						if ($code == 250) {
							$validated = 1;
						}

						// say goodbye regardless
						$message = "QUIT\r\n";
						fwrite($socket, $message);

						$buffer .= fgets($socket, 2048);

						$response = trim($buffer, "\r\n");
						$buffer = null;

						list($code, $message) = preg_split("/\s+/", $response, 2);

						return $validated;
					}
				}
			}
		}
	}

	class ValidateEmailException extends \Exception {}
