<?php
	namespace ServiceTo;

	use Exception;

	class ValidateEmail {
		/**
		 * The address to supply in our MAIL FROM connection to the SMTP servers we're talking to.
		 *
		 * @var string
		 */
		private $testaddress = "validateemail@service.to";

		/**
		 * Alias to lookup
		 *
		 * @param  string  $emailaddress  email address to test
		 * @return boolean
		 */
 		function test($emailaddress) {
			return $this->lookup($emailaddress);
		}

		/**
		 * Lookup all MX records and check each until we have a success
		 *
		 * @param  string  $emailaddress  email address to look up
		 * @return boolean
		 */
		function lookup($emailaddress) {
			list($user, $domain) = preg_split("/@/", trim($emailaddress));

			if ($user == "") {
				throw new ValidateEmailException("Blank user name");
			}
			if ($domain == "") {
				throw new ValidateEmailException("Blank domain name");
			}

			$mxhosts = array();
			$weight = array();
			if (getmxrr($domain, $mxhosts, $weight)) {
				// pick first one and check it.

				array_multisort($weight, $mxhosts);

				foreach ($mxhosts as $id => $mxhost) {
					if ($this->verify($emailaddress, $mxhost)) {
						return true;
					}
				}
			}
			else {
				throw new ValidateEmailException("No MX records");
			}
		}

		/**
		 * Connect to the mail server on port 25 and see if it allows mail for the users' supplied email address.
		 *
		 * @param  string  $emailaddress  email address to test
		 * @param  string  $mxhost        mail server host name to connect to and test
		 * @return boolean
		 */
		function verify($emailaddress, $mxhost) {
			$validated = false;

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
							$validated = true;
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

	class ValidateEmailException extends Exception {}