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
        $this->RegisterVariableFloat('Temperature', 'Temperatur');
        $this->RegisterVariableFloat('Humidity', 'Luftfeuchtigkeit');
        $this->RegisterVariableFloat('Pressure', 'Luftdruck');
        $this->RegisterVariableFloat('Battery', 'Batterie');
        $this->RegisterVariableInteger('CO2', 'CO2');
        $this->RegisterVariableInteger('VOC', 'VOC');
        $this->RegisterVariableInteger('RadonST', 'Radon (Short Term)');
        $this->RegisterVariableInteger('RadonLT', 'Radon (Long Term)');
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
        if (@IPS_VariableExists($this->GetIDForIdent('Temperature'))) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('Temperature'), [
                'PRESENTATION' => 1,
                'SUFFIX'       => ' °C',
                'ICON'         => 'Temperature'
            ]);
        }
        
        // Luftfeuchtigkeit
        if (@IPS_VariableExists($this->GetIDForIdent('Humidity'))) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('Humidity'), [
                'PRESENTATION' => 1,
                'SUFFIX'       => ' %',
                'ICON'         => 'Drops'
            ]);
        }
        
        // Luftdruck
        if (@IPS_VariableExists($this->GetIDForIdent('Pressure'))) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('Pressure'), [
                'PRESENTATION' => 1,
                'SUFFIX'       => ' hPa',
                'ICON'         => 'Gauge'
            ]);
        }

        // Batterie
        if (@IPS_VariableExists($this->GetIDForIdent('Battery'))) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('Battery'), [
                'PRESENTATION' => 1,
                'SUFFIX'       => ' V', // ESPHome gives voltage by default
                'ICON'         => 'Battery'
            ]);
        }

        // CO2
        if (@IPS_VariableExists($this->GetIDForIdent('CO2'))) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('CO2'), [
                'PRESENTATION' => 1,
                'SUFFIX'       => ' ppm',
                'ICON'         => 'Wind'
            ]);
        }

        // VOC
        if (@IPS_VariableExists($this->GetIDForIdent('VOC'))) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('VOC'), [
                'PRESENTATION' => 1,
                'SUFFIX'       => ' ppb',
                'ICON'         => 'Wind'
            ]);
        }

        // Radon ST / LT
        if (@IPS_VariableExists($this->GetIDForIdent('RadonST'))) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('RadonST'), [
                'PRESENTATION' => 1,
                'SUFFIX'       => ' Bq/m³',
                'ICON'         => 'Radiation'
            ]);
        }
        if (@IPS_VariableExists($this->GetIDForIdent('RadonLT'))) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('RadonLT'), [
                'PRESENTATION' => 1,
                'SUFFIX'       => ' Bq/m³',
                'ICON'         => 'Radiation'
            ]);
        }
    }

    public function ReceiveData($JSONString): string
    {
        $data = json_decode($JSONString);
        
        // Standard MQTT Splitter payload structure in IP-Symcon
        if (!isset($data->Topic) || !isset($data->Payload)) {
            return "NOK";
        }

        $topic = $data->Topic;
        $payload = $data->Payload;
        $base = $this->ReadPropertyString('MQTTBaseTopic');

        IPS_LogMessage('AirthingsWavePlus', 'Received Topic: ' . $topic . ' | Payload: ' . $payload);

        // Check if the topic belongs to us (e.g. "airthings01/sensor/waveplus_temperature/state")
        if (strpos($topic, $base) !== false) {
            $value = floatval($payload);
            
            // Map ESPHome default topic names to variables
            if (strpos($topic, 'temperature') !== false && @$this->GetIDForIdent('Temperature') !== false) {
                $this->SetValue('Temperature', $value);
            } elseif (strpos($topic, 'humidity') !== false && @$this->GetIDForIdent('Humidity') !== false) {
                $this->SetValue('Humidity', $value);
            } elseif (strpos($topic, 'pressure') !== false && @$this->GetIDForIdent('Pressure') !== false) {
                $this->SetValue('Pressure', $value);
            } elseif (strpos($topic, 'battery') !== false && @$this->GetIDForIdent('Battery') !== false) {
                $this->SetValue('Battery', $value);
            } elseif (strpos($topic, 'co2') !== false && @$this->GetIDForIdent('CO2') !== false) {
                $this->SetValue('CO2', (int)$value);
            } elseif ((strpos($topic, 'voc') !== false || strpos($topic, 'tvoc') !== false) && @$this->GetIDForIdent('VOC') !== false) {
                $this->SetValue('VOC', (int)$value);
            } elseif (strpos($topic, 'radon_long_term') !== false && @$this->GetIDForIdent('RadonLT') !== false) {
                $this->SetValue('RadonLT', (int)$value);
            } elseif (strpos($topic, 'radon') !== false && @$this->GetIDForIdent('RadonST') !== false) {
                $this->SetValue('RadonST', (int)$value);
            }
        }

        return "OK";
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
