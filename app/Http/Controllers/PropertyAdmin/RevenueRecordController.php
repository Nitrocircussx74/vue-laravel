<?php namespace App\Http\Controllers\PropertyAdmin;
use Request;
use Illuminate\Routing\Controller;
use Auth;
use File;
use Redirect;
use Storage;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use App\Http\Controllers\PushNotificationController;
use App;
# Model
use DB;
use App\Invoice;
use App\Transaction;
use App\InvoiceFile;
use App\PropertyUnit;
use App\Property;
use App\Bank;
use App\BankTransaction;

class RevenueRecordController extends Controller {

	public function __construct () {
		$this->middleware('auth:menu_revenue_record');
		view()->share('active_menu', 'bill');
		if(Auth::check() && Auth::user()->role == 2) Redirect::to('feed')->send();
	}

	public function index (Request $form) {

		$bills = Invoice::where('property_id','=',Auth::user()->property_id)->where('is_revenue_record',true);
		$bills = $bills->where('type',1)->orderBy('created_at','desc')->paginate(50);

		if(!$form::ajax()) {
			return view('revenue_record.receipt-list')->with(compact('bills'));
		} else {
			return view('revenue_record.receipt-list-element')->with(compact('bills'));
		}

	}

	public function viewReceipt ($id) {
        $bill = Invoice::with('transaction', 'invoiceFile')->find($id);
		return view('revenue_record.view-record')->with(compact('bill'));
	}

	public function create () {
		if(Request::isMethod('post')) {
			//dd(Request::all());
			$tax = Request::get('tax')?Request::get('tax'):0;
			$for_external_payer = false;
			
			$property 	= Property::find(Auth::user()->property_id);
			$discount = str_replace(',', '', Request::get('discount'));

				// Fro exaternal payee
			$invoice = new Invoice;
			$invoice->fill(Request::all());
			$invoice->tax 				= $tax;
			$invoice->type 				= 1;
			$invoice->property_id 		= Auth::user()->property_id;
			//$invoice->receipt_no 		= ++$property->receipt_counter;
			$invoice->transfer_only 	= (Request::get('transfer_only'))?true:false;
			$invoice->for_external_payer 	= false;
			$invoice->payer_name 		= Request::get('payer_name');
			$invoice->discount 			= $discount;
			$invoice->payment_status	= 2;
			$invoice->final_grand_total = $invoice->grand_total;
			$invoice->submit_date		= date('Y-m-d');
			$invoice->due_date			= $invoice->payment_date;
			$invoice->is_revenue_record	= true;
			$invoice->created_by        = $invoice->approved_by	= Auth::user()->id;
			if($invoice->payment_type == 2) {
				$invoice->bank_transfer_date = $invoice->payment_date;
				$invoice->transfered_to_bank = true;
			}
			$invoice->save();
			$trans = [];
			foreach (Request::get('transaction') as $t) {
				$trans[] = new Transaction([
					'detail' 	=> $t['detail'],
					'quantity' 	=> str_replace(',', '', $t['quantity']),
					'price' 	=> str_replace(',', '', $t['price']),
					'total' 	=> $t['total'],
					'transaction_type' 	=> $invoice->type,
					'property_id' 		=> Auth::user()->property_id,
					'for_external_payer' => false,
					'category' 			=> $t['category'],
					'due_date'			=> $invoice->payment_date,
					'payment_date'		=> $invoice->payment_date,
					'submit_date'		=> $invoice->submit_date,
					'payment_status' 	=> true,
					'bank_transfer_date'  => $invoice->bank_transfer_date
				]);
			}
			$invoice->transaction()->saveMany($trans);
			// Save attachments
			if(!empty(Request::get('attachment'))) {
				$attach = [];
				foreach (Request::get('attachment') as $key => $file) {
					//Move Image
					$path = $this->createLoadBalanceDir($file['name']);
					$attach[] = new InvoiceFile([
							'name' => $file['name'],
							'url' => $path,
							'file_type' => $file['mime'],
							'is_image'	=> $file['isImage'],
							'original_name'	=> $file['originalName']
					]);
				}
				$invoice->invoiceFile()->saveMany($attach);
			}

			// Save Bank transfer transaction
			if($invoice->payment_type == 2) {
				$bt = new BankTransaction;
				$bt->saveBankRevenueTransaction($invoice,Request::get('bank_id'));
				$bank = new Bank;
				$bank->updateBalance (Request::get('bank_id'),$invoice->final_grand_total);
			}

			return redirect('admin/revenue-record');
		}

		$bank = new Bank;
	    $bank_list = $bank->getBankList();

		return view('revenue_record.create-receipt')->with(compact('bank_list'));
	}

	public function removeFile ($name) {
		$folder = substr($name, 0,2);
		$file_path = 'bills/'.$folder."/".$name;
		if(Storage::disk('s3')->has($file_path)) {
			Storage::disk('s3')->delete($file_path);
		}
	}

	public function createLoadBalanceDir ($name) {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
		$folder = substr($name, 0,2);
		$pic_folder = 'bills/'.$folder;
        $directories = Storage::disk('s3')->directories('bills'); // Directory in Amazon
        if(!in_array($pic_folder, $directories))
        {
            Storage::disk('s3')->makeDirectory($pic_folder);
        }
        $full_path_upload = $pic_folder."/".$name;
        $upload = Storage::disk('s3')->put($full_path_upload, file_get_contents($targetFolder.$name), 'public');
        File::delete($targetFolder.$name);
		return $folder."/";
	}
}
