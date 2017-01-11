# Charcoal Social Scraper

Provides support for querying social media content and saving results as Charcoal Models.

## Usage

From your application's service provider:

```php
$container['instagram/client'] = function (Container $container) {
    $config = $container['config']['apis.instagram.auth'];

    $client = new InstagramClient(
        $config['client_id'],
        $config['client_secret'],
        sprintf('{"access_token":"%s"}', $config['access_token'])
    );

    return $client;
};

$container['twitter/client'] = function (Container $container) {
    $config = $container['config']['apis.twitter.auth'];

    $client = new TwitterClient(
        $config['consumer_key'],
        $config['consumer_secret'],
        $config['access_token'],
        $config['access_token_secret']
    );

    return $client;
};

$container['charcoal/social/scrapers'] = function (Container $container) {
    $parentContainer = $container;
    $scrapers = new Container();

    $scrapers['instagram'] = function (Container $container) use ($parentContainer) {
        $config = $parentContainer['config']['apis.instagram'];

        $scraper = new InstagramScraper([
            'client'   => $parentContainer['instagram/client'],
            'requests' => [
                'default' => [
                    'repository' => 'users',
                    'method'     => 'getMedia',
                    'filters'    => [
                        'id' => 'self'
                    ]
                ]
            ],
            'model_data' => [
                'media' => [
                    'active' => false
                ]
            ],
            'model_factory' => $parentContainer['model/factory']
        ]);

        return $scraper;
    };

    $scrapers['twitter'] = function (Container $container) use ($parentContainer) {
        $config = $parentContainer['config']['apis.twitter'];

        $scraper = new TwitterScraper([
            'client'   => $parentContainer['twitter/client'],
            'requests' => [
                'default' => [
                    'repository' => 'statuses',
                    'method'     => 'user_timeline',
                    'filters'    => [
                        'user_id' => 12345
                    ]
                ],
                'foobar' => [
                    'repository' => 'search',
                    'method'     => 'tweets',
                    'filters'    => [
                        'q' => '#foobar AND from:12345',
                        'include_entities' => true
                    ]
                ]
            ],
            'default_request' => 'foobar',
            'model_map' => [
                'tweet' => 'my-custom/object/tweet'
            ],
            'model_factory' => $parentContainer['model/factory']
        ]);

        return $scraper;
    };

    return $scrapers;
};
```

In this example, the scrapers are defined within a separate service container assigned to `$container['charcoal/social/scrapers']`. The `charcoal/social/scrapers` entry is required by scraper actions and scripts provided by this package.
