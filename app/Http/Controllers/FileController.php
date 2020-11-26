<?php namespace App\Http\Controllers;
use Request;
use Illuminate\Routing\Controller;
use File;
class FileController extends Controller {

	public function upload () {
		$targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
        if(!file_exists($targetFolder))
        {
           $dir = File::makeDirectory($targetFolder, 0777, true, true);
        }
        if (!empty(Request::hasFile('file'))) //originalName
        {
            $name =  md5(Request::file('file')->getFilename());//getClientOriginalName();
            $extension = strtolower(Request::file('file')->getClientOriginalExtension());
            $targetName = $name.".".$extension;
            if(Request::file('file')->move($targetFolder,$targetName)) {
                //if image
                $isImage = 0;
                if(in_array($extension, ['jpeg','jpg','gif','png'])) {
            	   $isImage = 1;
                   $this->reduceImgQuality($name,$extension,$targetFolder);
                }
        		return response()->json([
                    'name'      => strtolower($targetName),
                    'mime'      => Request::file('file')->getClientMimeType(),
                    'oldname'   => Request::file('file')->getClientOriginalName(),
                    'isImage'   => $isImage
                    ]
                );
        	}else return '';
        }
	}

    public function uploadProfileImg () {
        $targetFolder = public_path().DIRECTORY_SEPARATOR.'upload_tmp'.DIRECTORY_SEPARATOR;
        if(!file_exists($targetFolder))
        {
           $dir = File::makeDirectory($targetFolder, 0777, true, true);
        }
        if (!empty(Request::hasFile('file'))) //originalName
        {
            $name =  md5(Request::file('file')->getFilename());//getClientOriginalName();
            $extension = strtolower(Request::file('file')->getClientOriginalExtension());
            $targetName = $name.".".$extension;
            if(Request::file('file')->move($targetFolder,$targetName)) {
                //if image
                $this->reduceImgQuality($name,$extension,$targetFolder);
                return response()->json(['name' => strtolower($targetName)]);
            }else return '';
        }
    }

	public function reduceImgQuality ($name,$ext,$path) {
		$pathName = $path.$name.".".$ext;
		switch (strtolower($ext))
        {
            case 'jpeg':
            case 'jpg':
                $source = imagecreatefromjpeg($pathName);
            break;
            case 'gif':
                $source = imagecreatefromgif($pathName);
            break;
            case 'png':
                //$source = imagecreatefrompng($pathName);
                $source = imagecreatefromstring(file_get_contents($pathName));
            break;
            default:
                die('Invalid image type');
        }
        $original_width = imagesx( $source );
        $original_height = imagesy( $source );

        if( $original_width > 1024 || $original_height > 1024 ){
        	// Remove original image
            $size = 1024;
        	File::delete($pathName);

            if($original_width > $original_height){
                $new_height = $size;
                $new_width = $new_height*($original_width/$original_height);
            }
            else if($original_height > $original_width){
                $new_width = $size;
                $new_height = $new_width*($original_height/$original_width);
            } else {
                $new_width = $size;
                $new_height = $size;
            }

            $new_width = round($new_width);
            $new_height = round($new_height);

            $smaller_image = ImageCreateTrueColor($new_width, $new_height);
            $tmp_img = ImageCreateTrueColor($size, $size);

            ImageCopyResampled($smaller_image, $source, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
            $pathName = strtolower($pathName);
	     	switch (strtolower($ext))
	        {
	            case 'jpeg':
	            case 'jpg':
	               imagejpeg($smaller_image,$pathName,80);
	            break;
	            case 'gif':
	                imagegif($smaller_image,$pathName);
	            break;
	            case 'png':
	                imagepng($smaller_image,$pathName,8);
	            break;
	            default:
	                die('Invalid image type');
	        }
	        imagedestroy($smaller_image);
	        chmod($pathName, 0777);
        }
        imagedestroy($source);
	}

    public function makeProfileImg ($name,$ext,$path) {
        $pathName = $path.$name.".".$ext;
        switch (strtolower($ext))
        {
            case 'jpeg':
            case 'jpg':
                $source = imagecreatefromjpeg($pathName);
            break;
            case 'gif':
                $source = imagecreatefromgif($pathName);
            break;
            case 'png':
                $source = imagecreatefrompng($pathName);
            break;
            default:
                die('Invalid image type');
        }
        $original_width = imagesx( $source );
        $original_height = imagesy( $source );

        // Remove original image
        //$size = 1024;
        File::delete($pathName);

        if($original_width > $original_height){
            $new_height = $size = $original_height;
            $new_width = $new_height*($original_width/$original_height);
        }
        else if($original_height > $original_width){
            $new_width = $size = $original_width;
            $new_height = $new_width*($original_height/$original_width);
        } else {
        //if($original_height == $original_width){
            $size = $original_width;
            $new_width = $original_width;
            $new_height = $original_height;
        }

        $new_width = round($new_width);
        $new_height = round($new_height);

        $smaller_image = ImageCreateTrueColor($new_width, $new_height);
        $tmp_img = ImageCreateTrueColor($size, $size);

        ImageCopyResampled($smaller_image, $source, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

        if($new_width>$new_height){
            $difference = $new_width-$new_height;
            $half_difference =  round($difference/2);
            ImageCopyResampled($tmp_img, $smaller_image, 0-$half_difference+1, 0, 0, 0, $size+$difference, $size, $new_width, $new_height);
        }
        if($new_height>$new_width){
            $difference = $new_height-$new_width;
            $half_difference =  round($difference/2);
            ImageCopyResampled($tmp_img, $smaller_image, 0, 0-$half_difference+1, 0, 0, $size, $size+$difference, $new_width, $new_height);
        }
        if($new_height == $new_width){
            ImageCopyResampled($tmp_img, $smaller_image, 0, 0, 0, 0, $size, $size, $new_width, $new_height);
        }

        imagedestroy($smaller_image);
        $pathName = strtolower($pathName);
        switch (strtolower($ext))
        {
            case 'jpeg':
            case 'jpg':
               imagejpeg($tmp_img,$pathName,100);
            break;
            case 'gif':
                imagegif($tmp_img,$pathName);
            break;
            case 'png':
                imagepng($tmp_img,$pathName,9);
            break;
            default:
                die('Invalid image type');
        }
        imagedestroy($tmp_img);
        chmod($pathName, 0777);
        imagedestroy($source);
    }
}
