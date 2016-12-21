<?php

namespace Charcoal\Instagram\Object;

// From Pimple
use Pimple\Container;

// From 'charcoal-core'
use Charcoal\Model\AbstractModel;

// From 'charcoal-support'
use Charcoal\Support\Model\ManufacturableModelTrait;
use Charcoal\Support\Model\ManufacturableModelCollectionTrait;

// From 'charcoal-instagram'
use Charcoal\SocialScraper\Object\AbstractPost;
use Charcoal\SocialScraper\Object\HasHashtagsInterface;
use Charcoal\SocialScraper\Object\HasHashtagsTrait;

use Charcoal\Instagram\Object\Tag;
use Charcoal\Instagram\Object\User;

/**
 * Instagram "{@link https://www.instagram.com/developer/endpoints/media/ Media}" Object
 */
class Media extends AbstractPost implements
    HasHashtagsInterface
{
    use HasHashtagsTrait;
    use ManufacturableModelTrait;
    use ManufacturableModelCollectionTrait;

    /**
     * The post's caption for the media (provided by third-party).
     *
     * @var string|null
     */
    protected $caption;

    /**
     * The main media source chosen for the post.
     *
     * @var string|null
     */
    protected $image;

    /**
     * The main media type. Differentiates between an image and video (provided by third-party).
     *
     * @var string|null
     */
    protected $type;

    /**
     * Inject dependencies from a DI Container.
     *
     * @param Container $container A dependencies container instance.
     * @return void
     */
    public function setDependencies(Container $container)
    {
        parent::setDependencies($container);

        $this->setCollectionLoader($container['model/collection/loader']);
        $this->setCollectionLoaderFactory($container['model/collection/loader/factory']);
        $this->setModelFactory($container['model/factory']);
    }

    /**
     * Retrieve the post's user.
     *
     * @return ModelInterface|null
     */
    public function user()
    {
        if ($this->user && !($this->user instanceof User)) {
            $this->user = $this->castTo($this->user, User::class);
        }

        return $this->user;
    }

    /**
     * Retrieve the post's caption.
     *
     * @return string|null
     */
    public function caption()
    {
        return $this->caption;
    }

    /**
     * Set the post's caption.
     *
     * @param  string $caption The caption.
     * @return self
     */
    public function setCaption($caption)
    {
        $this->caption = $caption;

        return $this;
    }

    /**
     * Retrieve the URL to the post's image.
     *
     * @return string
     */
    public function image()
    {
        return $this->image;
    }

    /**
     * Retrieve the URL to the post's image.
     *
     * @param  string $image The main image/video.
     * @return self
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Set the post's media type.
     *
     * Possible media types:
     * - `image`
     * - `video`
     *
     * @param  string $type A media type.
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Retrieve the post's media type.
     *
     * @return string
     */
    public function type()
    {
        return $this->type;
    }

    /**
     * Retrieve the post's URL
     *
     * @return string|null
     */
    public function url()
    {
        $data = $this->rawData();
        return (isset($data['link']) ? $data['link'] : null);
    }
}
