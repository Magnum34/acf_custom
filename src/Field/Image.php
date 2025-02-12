<?php

namespace Corcel\Acf\Field;

use Corcel\Model\Post;
use Corcel\Model\Meta\PostMeta;
use Corcel\Acf\FieldInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class Image.
 *
 * @author Junior Grossi
 */
class Image extends BasicField implements FieldInterface
{
    /**
     * @var int
     */
    public $width;

    /**
     * @var int
     */
    public $height;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $mime_type;

    /**
     * @var array
     */
    protected $sizes = [];


    public $alt;

    /**
     * @var bool
     */
    protected $loadFromPost = false;

    /**
     * @param string $field
     */
    public function process($field)
    {
        $attachmentId = $this->fetchValue($field);

        $connection = $this->post->getConnectionName();

        if ($attachment = Post::on($connection)->find(intval($attachmentId))) {
            $this->fillFields($attachment);

            $imageData = $this->fetchMetadataValue($attachment);

            if($imageData){
                $this->fillMetadataFields($imageData);
            }else{
                $this->fillMetadataFieldsSVG($attachment);
            }
        }
    }

    /**
     * @return Image
     */
    public function get()
    {
        return $this;
    }

    /**
     * @param Post $attachment
     */
    protected function fillFields(Post $attachment)
    {


        $meta = PostMeta::where('post_id', $attachment->ID)
                        ->where('meta_key', '_wp_attachment_metadata')
                        ->first();
        if($meta){
            $meta =  unserialize($meta->meta_value);
        }else{
            $meta = "";
        }
        $alt =  \Corcel\Model\Meta\PostMeta::where(['post_id' => $attachment->ID,'meta_key' => '_wp_attachment_image_alt'])->first();
        $this->attachment = $meta;

        $this->mime_type = $attachment->post_mime_type;
        $this->url = $attachment->guid;
        $this->description = $attachment->post_excerpt;
        if($alt){
            $this->alt = $alt->value;
        }else{
            $this->alt = null;
        }
    }

    /**
     * @param string $size
     * @param bool $useOriginalFallback
     *
     * @return Image
     */
    public function size($size, $useOriginalFallback = false)
    {
        if (isset($this->sizes[$size])) {
            return $this->fillThumbnailFields($this->sizes[$size]);
        }

        return $useOriginalFallback ? $this : $this->fillThumbnailFields($this->sizes['thumbnail']);
    }

    /**
     * @param array $data
     *
     * @return Image
     */
    protected function fillThumbnailFields(array $data)
    {
        $size = new static($this->post);
        $size->filename = $data['file'];
        $size->width = $data['width'];
        $size->height = $data['height'];
        $size->mime_type = $data['mime-type'];

        $urlPath = dirname($this->url);
        $size->url = sprintf('%s/%s', $urlPath, $size->filename);

        return $size;
    }

    /**
     * @param Post $attachment
     *
     * @return array
     */
    protected function fetchMetadataValue(Post $attachment)
    {

        $meta = PostMeta::where('post_id', $attachment->ID)
                        ->where('meta_key', '_wp_attachment_metadata')
                        ->first();
        if($meta){
            $data = unserialize($meta->meta_value);
            $alt =  \Corcel\Model\Meta\PostMeta::where(['post_id' => $attachment->ID,'meta_key' => '_wp_attachment_image_alt'])->first();
            if($alt){
                $data['alt'] = $alt->value;
            }else{
                 $data['alt'] = null;
            }
           
           
            return $data;
        }
        return false;
    }

    /**
     * @param Collection $attachments
     *
     * @return Collection|array
     */
    protected function fetchMultipleMetadataValues(Collection $attachments)
    {
        $ids = $attachments->pluck('ID')->toArray();
        $metadataValues = [];

        $metaRows = PostMeta::whereIn("post_id", $ids)
            ->where('meta_key', '_wp_attachment_metadata')
            ->get();

        foreach ($metaRows as $meta) {
            $metadataValues[$meta->post_id] = unserialize($meta->meta_value);
        }

        return $metadataValues;
    }

    /**
     * @param array $imageData
     */
    protected function fillMetadataFields(array $imageData)
    {
        $this->filename = basename($imageData['file']);
        $this->width = $imageData['width'];
        $this->height = $imageData['height'];
        $this->sizes = $imageData['sizes'];
        $this->alt = $imageData['alt'];
    }
    /**
     * @param Post $attachment
     *
     * @return array $imageData
     */
    public function fillMetadataFieldsSVG(Post $attachment){

        $file = PostMeta::where('post_id', $attachment->ID)
                        ->where('meta_key', '_wp_attached_file')
                        ->first();
        if($file){
            $this->filename = basename($file);
            $this->width = false;
            $this->height = false;
            $this->sizes = false;
        }
    }
}
