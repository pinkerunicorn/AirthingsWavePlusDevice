<?php

declare(strict_types=1);

class AirthingsWavePlus extends IPSModuleStrict
{
    public function Create(): void
    {
        // Never delete this line!
        parent::Create();

        // Properties for MQTT
        $this->RegisterPropertyString('MQTTBaseTopic', 'airthings01');
        $this->SetReceiveDataFilter('.*' . preg_quote($this->ReadPropertyString('MQTTBaseTopic')) . '.*');

        // Variables
        $this->RegisterVariableFloat('AirTemp', 'Temperatur');
        $this->RegisterVariableFloat('AirHum', 'Luftfeuchtigkeit');
        $this->RegisterVariableFloat('AirPress', 'Luftdruck');
        $this->RegisterVariableFloat('AirBatt', 'Batterie');
        $this->RegisterVariableInteger('AirCO2', 'CO2');
        $this->RegisterVariableInteger('AirVOC', 'VOC');
        $this->RegisterVariableInteger('AirRadonST', 'Radon (Short Term)');
        $this->RegisterVariableInteger('AirRadonLT', 'Radon (Long Term)');
    }

    public function ApplyChanges(): void
    {
        // Never delete this line!
        parent::ApplyChanges();

        // Register MQTT Filter
        $topic = $this->ReadPropertyString('MQTTBaseTopic');
        $this->SetReceiveDataFilter('.*' . preg_quote($topic) . '.*');

        // Apply Symcon 8 Custom Presentations (instead of Legacy Profiles)
        $this->UpdatePresentations();
    }

    private function UpdatePresentations(): void
    {
        // Temperatur
        if (@IPS_GetObjectIDByIdent('AirTemp', $this->InstanceID) !== false) {
            $ok = IPS_SetVariableCustomPresentation($this->GetIDForIdent('AirTemp'), [
                'SUFFIX'       => ' °C',
                'ICON'         => 'Temperature'
            ]);
            IPS_LogMessage('AirthingsWavePlus', 'UpdatePresentations AirTemp: ' . ($ok ? 'OK' : 'FAIL'));
        }
        
        // Luftfeuchtigkeit
        if (@IPS_GetObjectIDByIdent('AirHum', $this->InstanceID) !== false) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AirHum'), [
                'SUFFIX'       => ' %',
                'ICON'         => 'Drops'
            ]);
        }
        
        // Luftdruck
        if (@IPS_GetObjectIDByIdent('AirPress', $this->InstanceID) !== false) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AirPress'), [
                'SUFFIX'       => ' hPa',
                'ICON'         => 'Gauge'
            ]);
        }

        // Batterie
        if (@IPS_GetObjectIDByIdent('AirBatt', $this->InstanceID) !== false) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AirBatt'), [
                'SUFFIX'       => ' V', // ESPHome gives voltage by default
                'ICON'         => 'Battery'
            ]);
        }

        // CO2
        if (@IPS_GetObjectIDByIdent('AirCO2', $this->InstanceID) !== false) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AirCO2'), [
                'SUFFIX'       => ' ppm',
                'ICON'         => 'Wind'
            ]);
        }

        // VOC
        if (@IPS_GetObjectIDByIdent('AirVOC', $this->InstanceID) !== false) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AirVOC'), [
                'SUFFIX'       => ' ppb',
                'ICON'         => 'Wind'
            ]);
        }

        // Radon ST / LT
        if (@IPS_GetObjectIDByIdent('AirRadonST', $this->InstanceID) !== false) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AirRadonST'), [
                'SUFFIX'       => ' Bq/m³',
                'ICON'         => 'Radiation'
            ]);
        }
        if (@IPS_GetObjectIDByIdent('AirRadonLT', $this->InstanceID) !== false) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AirRadonLT'), [
                'SUFFIX'       => ' Bq/m³',
                'ICON'         => 'Radiation'
            ]);
        }
    }

    public function ReceiveData(string $JSONString): string
    {
        try {
            $data = json_decode($JSONString);
            
            // Standard MQTT Splitter payload structure in IP-Symcon
            if (!isset($data->Topic) || !isset($data->Payload)) {
                return "NOK";
            }

            $topic = $data->Topic;
            
            // IP-Symcon MQTT Splitter passes Payload as HEX string
            $payloadRaw = is_scalar($data->Payload) ? (string)$data->Payload : '';
            $payloadStr = (ctype_xdigit($payloadRaw) && !empty($payloadRaw)) ? hex2bin($payloadRaw) : $payloadRaw;
            
            $base = $this->ReadPropertyString('MQTTBaseTopic');

            IPS_LogMessage('AirthingsWavePlus', 'Received Topic: ' . $topic . ' | Payload: ' . $payloadStr);

            // Check if the topic belongs to us (e.g. "airthings01/sensor/waveplus_temperature/state")
            if (strpos($topic, $base) !== false) {
                $value = floatval($payloadStr);
                
                // Map ESPHome default topic names to variables
                // Use @IPS_GetObjectIDByIdent instead of GetIDForIdent to avoid Exceptions in Strict Mode
                if (strpos($topic, 'temp') !== false && @IPS_GetObjectIDByIdent('AirTemp', $this->InstanceID) !== false) {
                    $this->SetValue('AirTemp', $value);
                } elseif (strpos($topic, 'hum') !== false && @IPS_GetObjectIDByIdent('AirHum', $this->InstanceID) !== false) {
                    $this->SetValue('AirHum', $value);
                } elseif (strpos($topic, 'press') !== false && @IPS_GetObjectIDByIdent('AirPress', $this->InstanceID) !== false) {
                    $this->SetValue('AirPress', $value);
                } elseif (strpos($topic, 'batt') !== false && @IPS_GetObjectIDByIdent('AirBatt', $this->InstanceID) !== false) {
                    $this->SetValue('AirBatt', $value);
                } elseif (strpos($topic, 'co2') !== false && @IPS_GetObjectIDByIdent('AirCO2', $this->InstanceID) !== false) {
                    $this->SetValue('AirCO2', (int)$value);
                } elseif ((strpos($topic, 'voc') !== false || strpos($topic, 'tvoc') !== false) && @IPS_GetObjectIDByIdent('AirVOC', $this->InstanceID) !== false) {
                    $this->SetValue('AirVOC', (int)$value);
                } elseif ((strpos($topic, 'radon_long_term') !== false || strpos($topic, 'radon_lt') !== false) && @IPS_GetObjectIDByIdent('AirRadonLT', $this->InstanceID) !== false) {
                    $this->SetValue('AirRadonLT', (int)$value);
                } elseif (strpos($topic, 'radon') !== false && @IPS_GetObjectIDByIdent('AirRadonST', $this->InstanceID) !== false) {
                    // Check if it's not the long term to avoid double matching
                    if (strpos($topic, 'long') === false && strpos($topic, 'lt') === false) {
                        $this->SetValue('AirRadonST', (int)$value);
                    }
                }
            }

            return "OK";
        } catch (Throwable $e) {
            IPS_LogMessage('AirthingsWavePlus', 'ReceiveData Exception: ' . $e->getMessage());
            return "NOK";
        }
    }

    public function RequestUpdate(): void
    {
        if (!$this->HasActiveParent()) {
            echo "Kein aktiver MQTT Server verbunden!";
            return;
        }

        $base = $this->ReadPropertyString('MQTTBaseTopic');
        $topic = $base . '/update/command';

        $data = [
            'DataID' => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType' => 3,
            'QualityOfService' => 0,
            'Retain' => false,
            'Topic' => $topic,
            'Payload' => 'PRESS'
        ];

        $this->SendDataToParent(json_encode($data));
        echo "Update-Anfrage an ESPHome gesendet ($topic)!";
    }
}
