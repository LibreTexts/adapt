<?php

namespace App;

use App\Helpers\Helper;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class Webwork extends Model
{

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->webwork_opl = app()->environment('production') ?
            "opl.libretexts.org"
            : "staging-opl.libretexts.org";


    }

    /**
     * @param string $problemJWT
     * @return array|mixed
     * @throws Exception
     */
    public function getSolution(string $problemJWT)
    {
        return $this->_getRenderApiResult($problemJWT, 'solution');
    }

    /**
     * @param string $problemJWT
     * @return array|mixed
     * @throws Exception
     */
    public function getHint(string $problemJWT)
    {
        return $this->_getRenderApiResult($problemJWT, 'hint');
    }

    /**
     * Shared logic for render-api endpoints that take a re-signed/re-encrypted
     * problemJWT (currently 'solution' and 'hint').
     *
     * @param string $problemJWT
     * @param string $type 'solution' or 'hint' — used as both the JWT 'typ' claim and the render-api endpoint
     * @return array|mixed
     * @throws Exception
     */
    private function _getRenderApiResult(string $problemJWT, string $type)
    {
        $jwe = new JWE();
        $secret = $jwe->getSecret('webwork');
        JWTAuth::getJWTProvider()->setSecret($secret);
        $claims = json_decode($jwe->decrypt($problemJWT, 'webwork'), 1);
        $claims['typ'] = $type;
        $allowedKeys = ['adapt', 'scheme_and_host', 'imathas', 'webwork', 'h5p', 'iss', 'aud', 'typ'];
        $claims = array_intersect_key($claims, array_flip($allowedKeys));
        $claims['problemJWT'] = $problemJWT;
        $token = JWTAuth::getJWTProvider()->encode($claims); //create the token
        $problemJWT = $jwe->encrypt($token, 'webwork'); //create the token
        $url = app()->environment('production') ?
            "render.libretexts.org"
            : "staging-render.libretexts.org";

        $fullUrl = "https://$url/render-api/$type?problemJWT=$problemJWT";
        $token = config('myconfig.webwork_token');
        $http_response = Http::withToken($token)->post($fullUrl);
        if ($http_response->successful()) {
            $response = $http_response->json();
            $response['type'] = $response['status'] === 200 ? 'success' : 'error';
        } else {
            $response['type'] = 'error';
            $response['message'] = "Could not get webwork" . ucfirst($type) . ": " . $http_response->body();
        }
        return $response;
    }

    /**
     * @param $code
     * @param $lengthThreshold
     * @return array|false|string[]
     */
    public function getRadioButtonLabels($code, $lengthThreshold)
    {
        // Step 1: Extract full RadioButtons(...) call
        $pos = strpos($code, 'RadioButtons(');
        if ($pos === false) return false;

        $start = $pos + strlen('RadioButtons(');
        $depth = 1;
        $len = strlen($code);
        $end = $start;

        while ($end < $len && $depth > 0) {
            $char = $code[$end];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            $end++;
        }

        if ($depth !== 0) return false;

        $inner = substr($code, $start, $end - $start - 1);

        // Step 2: Find first array (assumed to be in square brackets)
        $arrayStart = strpos($inner, '[');
        if ($arrayStart === false) return false;

        $depth = 1;
        $i = $arrayStart + 1;
        $arrayLen = strlen($inner);
        while ($i < $arrayLen && $depth > 0) {
            $char = $inner[$i];
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
            }
            $i++;
        }

        if ($depth !== 0) return false;

        $arrayString = substr($inner, $arrayStart, $i - $arrayStart); // includes []

        // Step 3: Extract quoted string elements using regex
        preg_match_all('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/', $arrayString, $matches);

        if (!isset($matches[1])) return false;
        if ($lengthThreshold) {
            $longElements = array_filter($matches[1], function ($el) use ($lengthThreshold) {
                return mb_strlen($el) > $lengthThreshold;
            });

            return !empty($longElements) ? $longElements : false;
        } else return $matches[1];
    }

    /**
     * @throws Exception
     */
    public function deletePath($removeFilePath)
    {


        $response = Http::withToken(config('myconfig.webwork_token'))
            ->delete("https://{$this->webwork_opl}/api/authored/problems/path/{$removeFilePath}");

        if ($response->failed()) {
            throw new Exception("Error deleting {$removeFilePath}: " . $response->body());
        }
    }

    /**
     * @throws Exception
     */
    public function listDir($dir)
    {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://wwlibrary.libretexts.org/render-api/cat?basePath=$dir");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = array();
        $headers[] = 'Authorization: Bearer ' . config('myconfig.webwork_token');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new Exception ("Error listing $dir:" . curl_error($curl));
        }

        return json_decode($response, 1);

    }

    /**
     * @throws Exception
     */
    public function storeQuestion($webwork_code, $webwork_dir)
    {
        $write_file_path = Helper::getWebworkCodePath() . "{$webwork_dir}/code.pg";

        $response = Http::withToken(config('myconfig.webwork_token'))
            ->post("https://{$this->webwork_opl}/api/authored/problems", [
                'file_path' => $write_file_path,
                'raw_source' => $webwork_code,
            ]);
        if ($response->failed()) {
            throw new Exception("Error storing question at {$write_file_path}: " . $response->body());
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function cloneDir($sourceDir, $targetDir)
    {

        $path = Helper::getWebworkCodePath();

        $response = Http::withToken(config('myconfig.webwork_token'))
            ->post("https://{$this->webwork_opl}/api/authored/clone", [
                'source_file_path' => $path . $sourceDir . '/code.pg',
                'target_file_path' => $path . $targetDir . '/code.pg',
            ]);
        if (!$response->successful()) {
            throw new Exception ("Could not clone webwork directory: " . $response->body());
        }
    }

    /**
     * @throws Exception
     */
    public function storeAttachment($filename, $file_contents, $webwork_dir, $mime_type = null)
    {
        $write_file_path = Helper::getWebworkCodePath() . "$webwork_dir/code.pg";
        $mime_type = $mime_type ?? 'application/octet-stream';

        $response = Http::withToken(config('myconfig.webwork_token'))
            ->attach('file', $file_contents, $filename, ['Content-Type' => $mime_type])
            ->post("https://{$this->webwork_opl}/api/authored/resources", [
                'problem_file_path' => $write_file_path,
            ]);
        if ($response->failed()) {
            throw new Exception("Error storing attachment {$filename}: " . $response->body());
        }

        return $response->json();
    }

    /**
     * @param $question_id
     * @param $question_revision_id
     * @return string
     */
    public function getDir($question_id, $question_revision_id): string
    {
        return $question_revision_id ? "$question_id-$question_revision_id" : $question_id;
    }


    /**
     * @param $value
     * @return string
     */
    public function inCodeSolution($value): string
    {
        if (
            strpos($value->webwork_code, 'BEGIN_SOLUTION') !== false &&
            (
                preg_match("/#-ULETH-#\s*BEGIN_SOLUTION/", $value->webwork_code) || // Match '#-ULETH-# BEGIN_SOLUTION'
                !preg_match("/#\s*BEGIN_SOLUTION/", $value->webwork_code) // Exclude lines starting with '# BEGIN_SOLUTION'
            )
        ) {
            // Extract all text between 'BEGIN_SOLUTION' and 'END_SOLUTION', excluding commented 'BEGIN_SOLUTION' lines
            if (preg_match("/(?<!#)\s*BEGIN_SOLUTION\s*(.*?)\s*END_SOLUTION/s", $value->webwork_code, $matches)) {
                return $matches[1]; // The solution text is in the first capturing group
            }
        }
        if (preg_match("/&SOLUTION\(EV3\(<<'EOT'\)\);\n(.*?)\nEOT/s", $value->webwork_code, $matches)) {
            return $matches[1];
        }
        return '';
    }


    /**
     * @param $value
     * @return bool
     */
    public function algorithmicSolution($value): bool
    {

        if (!$value->webwork_code) {
            return false;
        }
        if (
            strpos($value->webwork_code, 'BEGIN_PGML_SOLUTION') !== false &&
            (
                strpos($value->webwork_code, '#-ULETH-#') !== false ||
                !preg_match('/(?<!#)#\s*BEGIN_PGML_SOLUTION/', $value->webwork_code)
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * Detects whether the PG code defines a hint, so the "Show Hint" preview
     * button can be conditionally shown.
     *
     * @param $value
     * @return bool
     */
    public function hasHint($value): bool
    {
        if (!$value->webwork_code) {
            return false;
        }
        return str_contains($value->webwork_code, 'BEGIN_PGML_HINT');
    }
}
