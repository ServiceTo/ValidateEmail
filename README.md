# ValidateEmail
Library to validate an email address against its mail servers by doing a name server lookup and then connecting to its MX records.

## Tired of those pesky fake email addresses in your submission forms?
Add this little bit of majesty to your form validation rules and your server will connect to the MX records and test the validity of the address the user has entered.

It typically only takes a moment to validate a working address and when an invalid address is entered it takes longer, which can be a win, slowing down annoying script kiddies.

## Usage
### Install using composer...
	composer require "service-to/validate-email"

### In a Laravel Controller
	use ServiceTo\ValidateEmail;

	public function store(Request $request) {
		$validateemail = new ValidateEmail;
		try {
			if (!$validateemail->test($request->input("email"))) {
				return redirect()->back()->withErrors(["email" => "Invalid Email Address"])
			}
		}
		catch (ValidateEmailException $vee) {
			return redirect()->back()->withErrors(["email" => "Invalid Email Address (" . $vee->getMessage() . ")"])
		}

		// rest of checks...
	}

### In plain old PHP
	require_once("vendor/autoload.php");
	use ServiceTo\ValidateEmail;

	$errors = array();
	$validateemail = new ValidateEmail;
	try {
		if (!$validateemail->test($_REQUEST["email"])) {
			$errors["email"] = "Invalid Email Address"];
		}
	}
	catch (ServiceTo\ValidateEmailException $vee) {
		$errors["email"] = "Invalid Email Address (" . $vee->getMessage() . ")";
	}

	if (count($errors) > 0) {
		// don't move on from here, give the user back some errors...
		header("Content-type: application/json");
		echo(json_encode(array("response" => "error", "errors" => $errors)));
		exit();
	}


