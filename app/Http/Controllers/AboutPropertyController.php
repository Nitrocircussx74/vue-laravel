<?php namespace App\Http\Controllers;
use Request;
use Illuminate\Routing\Controller;
use Illuminate\Pagination\Paginator;
use League\Flysystem\AwsS3v2\AwsS3Adapter;
use Auth;
use Storage;
# Model
use App\Property;
use App\PropertyFile;
class AboutPropertyController extends Controller {

	public function __construct () {
		$this->middleware('auth');
		view()->share('active_menu','about');
	}

	public function getAttach ($id) {
		$file = PropertyFile::find($id);
        $file_path = 'property-file'.'/'.$file->url.$file->name;
        $exists = Storage::disk('s3')->has($file_path);
        if ($exists) {
            $response = response(Storage::disk('s3')->get($file_path), 200, [
                'Content-Type' => $file->file_type,
                'Content-Length' => Storage::disk('s3')->size($file_path),
                'Content-Description' => 'File Transfer',
                'Content-Disposition' => "attachment; filename={$file->original_name}",
                'Content-Transfer-Encoding' => 'binary',
            ]);
            ob_end_clean();
            return $response;
        }
	}
}
