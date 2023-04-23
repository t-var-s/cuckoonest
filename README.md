## 🐦cuckoonest

Archive current tweets from any profile to a JSON endpoint with the respective images. Currently scrapes them every couple of hours from nitter.net accumulating any new posts that are found. Each username is a subfolder of a folder called `nests` that can be hosted online. Each subfolder has an `index.php` that gives a JSON response with the latest accumulated posts. This also gets saved to a `status.json` file that can be requested if there's no need to check for any new posts. Related images are  also downloaded to a subfolder, each archived profile is self-contained. For example: 

![cuckoonest](https://user-images.githubusercontent.com/6660327/233867541-7894615c-94d4-4b20-a35c-d6db4043c4e3.gif)

Requirements are PHP 5.3+ with [composer](https://getcomposer.org/) to install the hQuery library. You can then run `php CuckooNest.php` with the usernames you'd like to scrape passed as arguments to create their respective folders.  
