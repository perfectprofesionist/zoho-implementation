<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Log;

use App\Http\Controllers\ErplySyncController;

use App\Models\ZohoAuthUser;

use App\Models\ZohoOrganization;

use App\Models\ZohoModules;

use App\Models\ZohoModuleFields;

class ZohoController extends Controller
{
	protected $erplyEndpoint = [
		'getProducts',
		'getCustomers',
		'getWebStore',
		'getPos',
		'getCompany',
		'getInventory'
	];

	public function triggerErplyEndpoints()
	{
        // Simulate some data
		foreach ($this->erplyEndpoint as $key => $value) {
       	// code...
			$erplyController = new ErplySyncController();
			$sync_type = $value;
			$data = [

				'sync_type' => $sync_type,
			];
			$request = Request::create(route('log.sync.request'), 'GET', $data);
			$erplyController->logSyncRequest($request);
		}


        // Create a new request object with the simulated data


        // Call the handleRequest method with the new request object
        // return $this->handleRequest($request);
	}
	public function authCallBack(Request $request)
	{
		$code = $request->code;

		// Define the URL and the payload
		$url = env('zoho_url').'/clientoauth/v2/10067233036/token';
		$payload = [
			'code' => $code,
			'grant_type' => env('grant_type'),
			'client_id' => env('client_id'),
			'client_secret' => env('client_secret'),
			'redirect_uri' => route('zoho.auth.callback'),
		];

		// Define the headers
		$headers = [
			'Content-Type' => 'application/x-www-form-urlencoded',
		];

		// Make the POST request using Laravel's HTTP client
		$response = Http::withHeaders($headers)->asForm()->post($url, $payload);

		// Get the response body
		$responseBody = $response->body();

		// Print the response
		// echo "<pre>".Auth::User()->id;

		$responseBody = json_decode($responseBody);


		$newZohoUser = new ZohoAuthUser();
		$erplyUser = Auth::User()->getSessionKey;
		$newZohoUser ->access_token = $responseBody->access_token;
		$newZohoUser ->refresh_token = $responseBody->refresh_token;
		$newZohoUser ->scope = $responseBody->scope;
		$newZohoUser ->token_type = $responseBody->token_type;
		$newZohoUser ->user_id = $erplyUser->id;
		$newZohoUser -> save();

		$this->triggerErplyEndpoints();

		$this->storeZohoCompanyData();

		$this->storeZohomModules();

		return redirect()->intended('user-dashboard');


	}

	public function getNewAccessToken()
	{
		$erplyUser = Auth::user()->getSessionKey;
		$zohoUser = ZohoAuthUser::where('user_id', $erplyUser->id)->first();

	    // Determine the timestamp to compare against
		$timestampToCheck = $zohoUser->updated_at ?? $zohoUser->created_at;

	    // Check if the timestamp is more than 30 minutes ago
		if ($timestampToCheck->lt(now()->subMinutes(30))) {
			$response = Http::asForm()->post(env('zoho_url').'/clientoauth/v2/'.env('portal_id').'/token', [
				'refresh_token' => $zohoUser->refresh_token,
				'client_id' => env('client_id'),
				'client_secret' => env('client_secret'),
				'grant_type' => 'refresh_token'
			]);

			$responseBody = json_decode($response->body());

	        // Update the access token and the updated_at timestamp
			$zohoUser->access_token = $responseBody->access_token;
			$zohoUser->updated_at = now();
			$zohoUser->save();

			return $responseBody->access_token;
		}

	    // Return the existing access token if the timestamp is not older than 30 minutes
		return $zohoUser->access_token;
	}

	public function getNewAccessTokenByUserId($userId)
	{	
		// $erplyUser = Auth::user()->getSessionKey;
		$zohoUser = ZohoAuthUser::where('id', $userId)->first();

	    // Determine the timestamp to compare against
		$timestampToCheck = $zohoUser->updated_at ?? $zohoUser->created_at;

	    // Check if the timestamp is more than 30 minutes ago
		if ($timestampToCheck->lt(now()->subMinutes(30))) {
			$response = Http::asForm()->post(env('zoho_url').'/clientoauth/v2/'.env('portal_id').'/token', [
				'refresh_token' => $zohoUser->refresh_token,
				'client_id' => env('client_id'),
				'client_secret' => env('client_secret'),
				'grant_type' => 'refresh_token'
			]);

			$responseBody = json_decode($response->body());

	        // Update the access token and the updated_at timestamp
			$zohoUser->access_token = $responseBody->access_token;
			
			$zohoUser->save();

			return $responseBody->access_token;
		}

	    // Return the existing access token if the timestamp is not older than 30 minutes
		return $zohoUser->access_token;
	}


	public function storeZohoCompanyData()
	{
    // Define the URL and headers
		$erplyUser = Auth::User()->getSessionKey;
		$zohoUser = ZohoAuthUser::where('user_id', $erplyUser->id)->first();
		// dd($erplyUser, $zohoUser);
		$access_token = $this->getNewAccessToken();
		$url = 'https://hpww.zohoplatform.com/crm/v4/org';
		$headers = [
			'Authorization' => 'Zoho-oauthtoken ' . $access_token,
		];

    // Send GET request
		$response = Http::withHeaders($headers)->get($url);
		$responseBody = json_decode($response->body());

		if (empty($responseBody->org)) {
			return response()->json(['message' => 'No organization data found'], 404);
		}

		$data = $responseBody->org[0];

    // Check if the organization data already exists
		$existingOrg = ZohoOrganization::where('org_id', $data->id)->first();
		if ($existingOrg) {
			return response()->json(['message' => 'Organization data already exists'], 409);
		}

		$orgData = new ZohoOrganization();
		$orgData->org_id = $data->id;
		$orgData->zgid = $data->zgid;
		$orgData->primary_email = $data->primary_email;
		$orgData->company_name = $data->company_name;
		$orgData->user_id = $zohoUser->id;
		$orgData->save();

		return response()->json(['message' => 'Organization data stored successfully'], 201);
	}

	public function storeZohomModules()
	{
		$erplyUser = Auth::user()->getSessionKey;

		$zohoUser = ZohoAuthUser::where('user_id', $erplyUser->id)->first();
		$access_token = $this->getNewAccessToken();

		$response = Http::withHeaders([
			'Authorization' => 'Zoho-oauthtoken ' . $access_token,
		])->get('https://hpww.zohoplatform.com/crm/v4/settings/modules');

		if (!$response->successful()) {
			return response()->json(['message' => 'Failed to fetch modules data'], $response->status());
		}

		$modules = collect(json_decode($response->body(), true)['modules'] ?? []);

		$filteredModules = $modules->filter(function ($module) {
			return $module['visible'] && $module['editable'];
		});

		$newModules = $filteredModules->reject(function ($module) {
			return ZohoModules::where('module_id', $module['id'])->exists();
		});

		$newModules->each(function ($module) use ($zohoUser) {
			$moduleData = new ZohoModules();
			$moduleData->fill([
				'plural_label' => $module['plural_label'],
				'singular_label' => $module['singular_label'],
				'module_name' => $module['module_name'],
				'api_name' => $module['api_name'],
				'module_id' => $module['id'],
				'visible' => $module['visible'],
				'editable' => $module['editable'],
				'user_id' => $zohoUser->id,
			]);
			$moduleData->save();
			$this->saveModuleFields($module['api_name']);
		});

		return response()->json(['message' => 'Modules data stored successfully'], 201);
	}

	public function saveModuleFields($moduleName)
	{
	    $erplyUser = Auth::user()->getSessionKey;

	    $zohoUser = ZohoAuthUser::where('user_id', $erplyUser->id)->orderBy('id', 'desc')->first();

	    $access_token = $this->getNewAccessToken();

	    $response = Http::withHeaders([
	        'Authorization' => 'Zoho-oauthtoken ' . $access_token,
	    ])->get('https://hpww.zohoplatform.com/crm/v4/settings/fields', [
	        'module' => $moduleName,
	        'include' => 'allowed_permissions_to_update'
	    ]);

	    Log::info($response->body());

	    if ($response->successful()) {
	        // Decode the response body into an associative array
	        $responseData = json_decode($response->body(), true);

	        // Extract the fields from the response data
	        $fields = $responseData['fields'] ?? [];

	        // Filter the fields based on the read_write permission
	        $result = array_filter($fields, function ($field) {
	            return isset($field['allowed_permissions_to_update']['read_write']) && $field['allowed_permissions_to_update']['read_write'];
	        });

	        // Save module fields
	        foreach ($result as $field) {
	            $zohoModuleFields = new ZohoModuleFields();
	            $zohoModuleFields->zoho_user_id = $zohoUser->id;
	            $zohoModuleFields->module_name = $moduleName;
	            $zohoModuleFields->module_field = $field['api_name'];
	            $zohoModuleFields->display_label = $field['display_label'];
				$zohoModuleFields->mandatory = $field['system_mandatory'];
	            $zohoModuleFields->save();
	        }
	    }
	}

	public function setModuleFielsHtml($zohoModulesFeildsData,$data)
	{

		$carry = '<option value="">Select Feilds</option>';
			foreach ($zohoModulesFeildsData as $key => $value){
				// return $value->module_name;
				$display_label = htmlspecialchars($value->display_label);
				$data_required = '';
				if($value->mandatory == '1'){
					$display_label = htmlspecialchars($value->display_label).' * ';
					$data_required = 'required';
				}
			$carry .='<option data-required="'.$data_required.'" value="' . htmlspecialchars($value->module_field) . '" data-value="'.$data.'">' . $display_label . '</option>';
			};

	        // Return the HTML options as the response
			return $carry;
	}

	public function getModuleFields($moduleName)
	{
	    // Get the currently authenticated user
		$erplyUser = Auth::user()->getSessionKey;

	    // Retrieve the latest ZohoAuthUser for the current user
		$zohoUser = ZohoAuthUser::where('user_id', $erplyUser->id)->orderBy('id', 'desc')->first();
		
		$zohoModulesFeildsData = ZohoModuleFields::where('zoho_user_id',$zohoUser->id)->where('module_name',$moduleName)->orderBy('display_label', "asc")->get();
	        // Generate the HTML options string
		return $zohoModulesFeildsData;
	

	    // Handle errors (optional)
	
	}

}