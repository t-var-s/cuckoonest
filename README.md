# 🐦 cuckoonest
Archive current tweets from any profile to a JSON endpoint with the respective images. Currently scrapes them hourly from nitter.net accumulating any new posts that are found. The folder called `nests` has a subfolder that can be hosted for each username. Each folder has an `index.php` that gives a JSON response with the latest accumulated posts. This also gets saved to a `status.json` file that can be requested if there's no need to check for any new posts. Related images are also downloaded to a subfolder. For example: 

![cuckoonest](https://user-images.githubusercontent.com/6660327/233867541-7894615c-94d4-4b20-a35c-d6db4043c4e3.gif)

Requirements are PHP 5.3+ with composer to install the hQuery library.  
