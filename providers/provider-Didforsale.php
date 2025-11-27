<?php
namespace FreePBX\modules\Smsconnector\Provider;

class Didforsale extends providerBase 
{
    public function __construct()
    {
        parent::__construct();
        $this->name     = _('DIDforsale');
        $this->nameRaw  = 'didforsale';

        $this->configInfo = array(
            'apikey' => array(
                'type'        => 'string', 
                'label'       => _('API Key'),
                'help'        => _("Your DIDforsale API Key."),
                'default'     => '',
                'class'       => '',
                'required'    => true, 
                'placeholder' => _('Enter your DIDforsale API Key'),
            ),
        );
    }
     
    public function sendMedia($id, $to, $from, $message=null)
    {
        // DIDforsale supports MMS via media_urls parameter in the Send SMS API
        // Media URLs are included in the request alongside text message
        return $this->sendMessage($id, $to, $from, $message, true);
    }

    public function sendMessage($id, $to, $from, $message=null, $includeMedia=false)
    {
        $config = $this->getConfig($this->nameRaw);
        
        if (empty($config['apikey'])) {
            return false;
        }

        // DIDforsale API Endpoint (V4 supports JSON with media_urls parameter)
        $url = 'https://api.didforsale.com/didforsaleapi/index.php/api/V4/SMS/Send';

        // Prepare Data
        $data = array(
            'apikey' => $config['apikey'],
            'from'   => $from,
            'to'     => $to,
            'text'   => $message
        );
        
        // Add media URLs if sending MMS
        if ($includeMedia) {
            $mediaUrls = $this->media_urls($id);
            if (!empty($mediaUrls)) {
                $data['media_urls'] = $mediaUrls;
            }
        }

        // Send Request via CURL with JSON body
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Parse JSON response and check for success
        $responseData = json_decode($response, true);
        if ($httpCode == 200 && isset($responseData['status']) && $responseData['status'] === true) {
            $this->setDelivered($id);
            return true;
        }

        // Log error response for debugging
        freepbx_log(FPBX_LOG_INFO, sprintf(_('DIDforsale Send Failed: HTTP %s, %s'), $httpCode, $response));

        return false;
    }

    public function callPublic($connector)
    {
        // DIDforsale sends parameters via GET or POST (usually GET for the URL forwarding)
        // Parameters are: 'from', 'to', 'text'
        
        $request = $_REQUEST;

        if (isset($request['from']) && isset($request['to']) && isset($request['text'])) {
            
            $from    = $request['from'];
            $to      = $request['to'];
            $message = $request['text'];

            // Pass to SMS Connector
            // receiveMessage($did, $from, $message)
            $this->receiveMessage($to, $from, $message);
            
            // Return success code to DIDforsale so they don't retry
            return 200;
        }

        // If we are here, it might be a status callback (initiated, ringing, etc)
        // We return 200 to acknowledge receipt and stop retries, even if we don't process it.
        return 200;
    }
}