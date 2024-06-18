## Install Instructions ##

Create mugopage table:

CREATE TABLE mugopage ( id int NOT NULL AUTO_INCREMENT, type varchar(128) NOT NULL, identifier varchar(128) NOT NULL, data text NOT NULL, PRIMARY KEY (id));

Copy mugopage.yaml.sample to root/config/routes/mugopage.yaml

Create a bundles folder below the root folder.

Move this project folder to bundles/Mugo/PageBundle.

Update root/config/bundles.php, add:

Mugo\PageBundle\MugoPageBundle::class => ['all' => true],

Update composer.json:

    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "": "bundles/"
        }
    },
Dump composer autoload:

php composer.phar dumpautoload

Regenerate symlinks
php bin/console assets:install --symlink --env=ENV

Clear caches
php bin/console cache:clear --env=ENV

Run yarn:

yarn encore dev|prod
yarn encore dev|prod --config-name=ibexa


Clear varnish cache:

sudo varnishadm "ban req.url ~ /*"


## DEV NOTES ##

The MugoPage Field Type is based on the Ibexa Text Field Type. The information is stored internally the same way as the Ibexa Text Field Type.

The main differences are:
- The edit view hides the textarea and stores a json string with all the information related to layouts, zones and blocks.
- It overrides the method "getRelations" so the we store all the related items ids that are associated with blocks.

Code needs still some cleanup so we remove non necessary lines that are completely related to the Text Field Type, for example "rows"/"textRows". It is still all there for reference.

All the configurations are stored in the database. We use the table "mugopage" to store settings for the base layouts, zones and blocks.

There is a link in the left menu, in the "Admin" section.

There are specific routes to manage each components:
- /mugopage/blocks
- /mugopage/layouts
- /mugopage/zones

It is possible to specify override templates for layouts and blocks. It must follow the Ibexa design path notation, example: @ibexadesign/mugopage/blocks/poc_block.html.twig.

If the block file is not found, the code falls back to the default template.

The default front end block for blocks and zones are different from the back end templates. They show less information.
