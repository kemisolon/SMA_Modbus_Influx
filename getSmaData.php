<?php
require_once dirname(__FILE__) . '/Phpmodbus/ModbusMaster.php'; 


//Influx 
$host_Influx = "http://localhost";
$port_Influx = 8086;
$databaseName = "messungen";

//Modbus 
$unitID = 3;
$SMA_IP_Adress = "192.168.12.146";

$SMA_register = array
  (
//  array(30521,"U64","FIX0","s","Einspeisezeit"),
  array(30513,"U64","FIX0","Wh","AC_Gesamtertrag"),
  array(30517,"U64","FIX0","Wh","AC_Tagesertrag"),

  	
  array(30201,"U32","ENUM","ENUM","Status"),
//  array(30517,"U32","FIX0","Wh","Daily_yield_abs"),
  array(30529,"U32","FIX0","Wh","Total_yield"),	//30529 - Total yield - Wh - U32 - FIX0 - RO
  array(30535,"U32","FIX0","Wh","Daily_yield"),	//30535 - Daily yield - Wh - U32 - FIX0 - RO
  array(30537,"U32","FIX0","kWh","Daily_yield_kWh"), //30537 - Daily yield kWh- kWh - U32 - FIX0 - RO
  array(30539,"U32","FIX0","MWh","Daily_yield_MWh"), //30539 - Daily yield MWh - MWh - U32 - FIX0 - RO

  array(30541,"U32","FIX0","s","Operating_Time"), //30541 - Operating Time - s - U32 - FIX0 - RO
  array(30543,"U32","FIX0","s","Feed-in_time"), //30543 - Feed-in Time - s - U32 - FIX0 - RO

  	array(30769,"U32","FIX3","A","DC_current_input1"),	//30769 - DC current input [1] - A - S32 - FIX3 - RO
  	array(30771,"U32","FIX2","V","DC_voltage_input1"),	//30771 - DC voltage input [1] - V - S32 - FIX2 - RO
  	array(30773,"U32","FIX0","W","DC_power_input1"),	//30773 - DC power input [1] - W - S32 - FIX0 - RO
	array(30777,"U32","FIX0","W","Power_L1"),	//30777 - Power L1 - W - S32 - FIX0 - RO
 	array(30779,"U32","FIX0","W","Power_L2"),	//30779 - Power L2 - W - S32 - FIX0 - RO
  	array(30781,"U32","FIX0","W","Power_L3"),	//30781 - Power L3 - W - S32 - FIX0 - RO
  	array(30953,"U32","TEMP","°C","Internal_temperature1"),	//30953 - Internal temperature - °C - S32 - TEMP - RO
  	array(30957,"U32","FIX3","A","DC_current_input2"),	//30957 - DC current input [2] - A - S32 - FIX3 - RO
  	array(30959,"U32","FIX2","V","DC_voltage_input2"),	//30959 - DC voltage input [2] - V - S32 - FIX2 - RO
  	array(30961,"U32","FIX0","W","DC_power_input2"),	//30961 - DC power input [2] - W - S32 - FIX0 - RO
//  array(31793,"U32","FIX3","A","DC_current_input1"),	//31793 - DC current input [1] - A - S32 - FIX3 - RO
//  array(31795,"U32","FIX3","A","DC_current_input2"),	//31795 - DC current input [2] - A - S32 - FIX3 - RO
//  array(34113,"S32","TEMP","°C","Internal_temperature2"),		//34113 - Internal temperature - °C - S32 - TEMP - RO
  array(30977,"U32","FIX3","A","Grid_current_phase_L1"),
  array(30979,"U32","FIX3","A","Grid_current_phase_L2"),
  array(30981,"U32","FIX3","A","Grid_current_phase_L3")
  );


$modbus = new ModbusMaster($SMA_IP_Adress, "TCP");
//$timestamp = (int) exec('date +%s%N');
try
{
	foreach ($SMA_register as $register)
	{
		$HexValue= "";
		if($register[1] == "S32") //signed integer
		{
			$RawValue = $modbus->readMultipleRegisters($unitID, $register[0], 2);
			$value=PhpType::bytes2signedInt($RawValue);
		}

		if($register[1] == "U32") //unsigned integer
		{
			$RawValue = $modbus->readMultipleRegisters($unitID, $register[0], 2);
			foreach ($RawValue as $byte) 
			{
				$HexValue = $HexValue.(string)dechex($byte);
			}
			if($HexValue == "80000") //NaN
			{
				continue;
			}
			$value = hexdec($HexValue);
		}

		if($register[1] == "U64") //unsigned integer
		{
			$RawValue = $modbus->readMultipleRegisters($unitID, $register[0], 4);
			foreach ($RawValue as $byte) 
			{
				$HexValue = $HexValue.(string)dechex($byte);
			}
			if($HexValue == "80000") //NaN
			{
				continue;
			}
			$value = hexdec($HexValue);
		}
		// Kommastellen
		if ($register[2] == "FIX1")//FIX1 Dezimalzahl, kaufmännisch gerundet, eine Nachkommastelle. 
		    $value /= (float) 10;
		if ($register[2] == "FIX2")//FIX2 Dezimalzahl, kaufmännisch gerundet, zwei Nachkommastellen. 
		    $value /= (float) 100; 
		if ($register[2] == "FIX3")//FIX3 Dezimalzahl, kaufmännisch gerundet, drei Nachkommastellen. 
		    $value /= (float) 1000;
		if ($register[2] == "TEMP")//
		    $value /= (float) 10;

		if (isset($argv[1]) && $argv[1] == "debug")
		{
			echo $register[4] . " \t ". $value ." ". $register[3]. "\n";
			echo "RAW Data (HEX) = ". $HexValue ."\n\n";
			var_dump($modbus);
		}
		else
		{
			$curlCMD = "curl -i -XPOST '" . $host_Influx . ":" . $port_Influx . "/write?db=" .$databaseName. "' --data-binary 'SolarInverter,Type=" . $register[4] . ",Unit=".$register[3]. " value=" . $value ."'";
			//echo $curlCMD;
			$result = exec($curlCMD);
		}

	}
}
catch (Exception $e) {

    echo $modbus;
    echo $e;
    exit;
}


?>
