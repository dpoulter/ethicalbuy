<?php

    require(__DIR__ . "/../includes/config.php");

    // numerically indexed array of places
    $symbols = [];
    // TODO: search database for symbols matching $_GET["symbol"]
  //  $rows = query("SELECT description FROM stock_symbols WHERE MATCH(symbol,description) AGAINST('".$_GET["symbol"]."')");
   //$rows = query("SELECT description FROM stock_symbols where symbol='AGA.L'");
	//for ($i=0;$i<count($rows);$i++)
		//array_push($symbols,$rows[$i]["description"]);
	 $symbols = ['AGAAGA', 'Alaska', 'Arizona', 'Arkansas', 'California',
  'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia', 'Hawaii',
  'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana',
  'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota',
  'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire',
  'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota',
  'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island',
  'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont',
  'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'];  
    // output shares as JSON (pretty-printed for debugging convenience)
    header("Content-type: application/json");
    print(json_encode($symbols));
   
?>
