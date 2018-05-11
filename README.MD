# Initialize the container
$docker run --name hesk -d --link mysql-container-name:mysql -p 80:80 brunoah/hesk

Where 'mysql-container-name' is the name of the container that is running the database that will be used.

# After startup
Go to http://your-host/install to initialize the installation

Where you-host is the server where you are running the Hesk container