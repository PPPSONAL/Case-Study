<?php
namespace Console\App\Commands;
 
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
// use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DomCrawler\Crawler;
use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;

class ETLExample extends Command
{
    protected function configure()
    {
        $this->setName('ETL')
            ->setDescription('XML FILE EXTRACT AND UPLOAD TO GOOGLE SPREAD SHEET!')
            ->addArgument('fileURL', InputArgument::OPTIONAL, '');
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
        $fileURL = $input->getArgument('fileURL');
        $fileURL = !empty( $fileURL )?  $fileURL : 'public/coffee_feed.xml';
        $xml = file_get_contents($fileURL); // get the content of file
        $crawler = new Crawler($xml); // extract the content in object  
        $crawler = $crawler->filter('body > div')->each(function (Crawler $node, $i) {
            return $node->text();
        });

        $values = [];
        foreach($crawler as $key =>$val){
            if(!empty($val)){
                $values[] = array(preg_replace('/[^A-Za-z0-9]/', ' ', $val)); //replace all special characters
            }
        }
        
        $client = $this->getClient();// Google authorisation
        $service = new Google_Service_Sheets($client);

        $spreadsheetId = "1a21z3Bpij1W6SorDXIw5wNY6S-lBEdV2TbNUH05AI4g";// already created Sheet ID
        $range = "Sheet1!A2";

        //Add Data to spreadsheet
        //https://docs.google.com/spreadsheets/d/1a21z3Bpij1W6SorDXIw5wNY6S-lBEdV2TbNUH05AI4g/edit?usp=sharing
        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => "RAW"
        ];
        $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
        echo $result->getUpdates()->getUpdatedCells() ."  cells appended.";
        }catch(Exception $e){
            //Log error
            return $e;
        }
        return 0;
    }

    function getClient()
    {
        try{
            $client = new Google_Client();
            $client->setApplicationName('Google Sheets API PHP Quickstart');
            $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
            $client->setAuthConfig('credentials.json');
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            // Load previously authorized token from a file, if it exists.
            // The file token.json stores the user's access and refresh tokens, and is
            // created automatically when the authorization flow completes for the first
            // time.
            $tokenPath = 'token.json';
            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $client->setAccessToken($accessToken);
            }

            // If there is no previous token or it's expired.
            if ($client->isAccessTokenExpired()) {
                // Refresh the token if possible, else fetch a new one.
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                } else {
                    // Request authorization from the user.
                    $authUrl = $client->createAuthUrl();
                    printf("Open the following link in your browser:\n%s\n", $authUrl);
                    print 'Enter verification code: ';
                    $authCode = trim(fgets(STDIN));

                    // Exchange authorization code for an access token.
                    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                    $client->setAccessToken($accessToken);

                    // Check to see if there was an error.
                    if (array_key_exists('error', $accessToken)) {
                        throw new Exception(join(', ', $accessToken));
                    }
                }
                // Save the token to a file.
                if (!file_exists(dirname($tokenPath))) {
                    mkdir(dirname($tokenPath), 0700, true);
                }
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            }
        }catch(Exception $e){
            //Log error
            return $e;
        }    
        return $client;
    }
}
