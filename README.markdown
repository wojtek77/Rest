## Overview

**REST client** for PHP.

This class is dependent on the ***pecl_http*** extension.

## Usage

#### Example for Google Maps:

	require_once './Rest.php';

	$address = 'Gizycko Poland';

	$rest = new Rest('http://maps.googleapis.com/maps/api/geocode/json');
	$result = $rest->get(
		'',
		array(
			'sensor' => 'false',
			'address' => $address,
		)
	);

	echo '<pre>'.print_r($result, true).'</pre>';