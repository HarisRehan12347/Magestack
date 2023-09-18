(Server Stack Information Script)
This PHP script is designed to gather information about various server stacks and components commonly used in web development environments. It retrieves information about the status, version, and usage of key software components such as Magento, web server, database, composer, PHP, Elasticsearch, Redis, RabbitMQ, Varnish, and Memcache.

How to Use
Ensure that you have PHP installed on your server.
Place the stack_info.php script on your server.
Modify the script's configuration if necessary. You can change the constant ENV_FILENAME to specify the location of the environment configuration file.
Execute the script by running it using the PHP interpreter from your command line or web browser.
Example (command line):
php stack_info.php
http://your-server.com/path-to-script/stack_info.php
The script will gather information about the specified server stacks and display it in a tabular format.

Configuration
You can modify the script's behavior by editing the constants and functions within the script:

ENV_FILENAME: Specifies the location of the environment configuration file.
STACKS: Defines the list of software components to be checked and their associated details.
STR: Defines specific stacks that are related to caching, search engines, and message queues.
Output
The script will display information about each software component, including its name, version, usage status, and success in gathering information. Additionally, any extra information or messages related to the component are displayed.

License
This script is provided under an open-source license (insert your preferred license here). You are free to modify and distribute it as needed.
