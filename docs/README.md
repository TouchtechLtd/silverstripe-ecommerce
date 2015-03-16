# Updating API?

http://phpdox.de/getting-started.html

### Bash script to run from the root dir of your site:

```sh
    cd /rootdir_of_website/
    svn delete ecommerce/docs/api --force
    wget http://phpdox.de/releases/phpdox.phar
    chmod +x phpdox.phar
    mv phpdox.phar /usr/local/bin/phpdox
    phpdox --version
    cd ./ecommerce/docs/en/phpdox/
    rm xml -rf
    phpdox
    cd /rootdir_of_website/
    rm phpdox.phar
    cd ecommerce
    svn add docs/api --force
    svn ci --message "updating documentation"

```

