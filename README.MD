# Rackle
Rackle is an open source blockchain explorer initially written for Omnicoin. It builds on top of bitcoin-ABE's database, and basically acts as an improvement to the simple web server it presents. Rackle is written in PHP and manages dependencies using Composer.

[![Code Climate](https://codeclimate.com/github/DoctorBlue/Rackle/badges/gpa.svg)](https://codeclimate.com/github/DoctorBlue/Rackle) [![Test Coverage](https://codeclimate.com/github/DoctorBlue/Rackle/badges/coverage.svg)](https://codeclimate.com/github/DoctorBlue/Rackle)

## Requirements
- A MySQL database
- Bitcoin-ABE

## Instructions
### The ABE loader
In order to set up Rackle, you will first need to get ABE up and running. You should start by following [their instructions](https://github.com/bitcoin-abe/bitcoin-abe). Once you're finished, you should make sure it keeps loading new blocks. You can either just start it normally, or loop it with no-serve. I run ABE with this configuration (loader.conf):

    dbtype MySQLdb
    connect-args {"user":"mysqluser", "db":"abe", "passwd":"changeme"}
    no-serve
    datadir+=[{
      "chain":"Omnicoin",
      "code3":"OMC",
      "dirname":"/home/omnicoind/.OmniCoin",
      "conf":"OmniCoin.conf",
      "rpcport":"4873",
      "address_version":"\u0073",
      "magic":"\u00d4\u00cb\u00a1\u00ef"
    }]

I then start ABE every 5 seconds using the following bash script:

    while :
    do
      python -m Abe.abe --config loader.conf
      sleep 5
    done

You can start this script in a screen session or with an @reboot cron. Even better you could write a simple init script for it (Please share it if you do).

### Configuring Rackle
Now that you got ABE loading data into your database, you can get Rackle running. Copy everything into a publicly accessible directory on your web server and copy the sample configuration file `rackle.sample.ini` to `../rackle.ini` (outside web root, if you want it somewhere else, just edit CONFIG_PATH on index.php:1). Open the file in a text editor and fill in your database information. You will also need to fill in the address version byte for your coin and ABE's internal ID. You can look this up in the database with a simple query:

    SELECT chain_id, chain_name, HEX(chain_address_version) FROM chain;

Sample output:

    +----------+------------+----------------------------+
    | chain_id | chain_name | HEX(chain_address_version) |
    +----------+------------+----------------------------+
    |        1 | Bitcoin    | 00                         |
    |        2 | Testnet    | 6F                         |
    |        3 | Namecoin   | 34                         |
    |        4 | Omnicoin   | 73                         |
    +----------+------------+----------------------------+

For Omnicoin, the chain ID would be 4 and the address version byte would be "73".

### Setting up rewrite rules
Rackle requires a rewrite rule in order to work. The following works for nginx:

	location / {
		try_files $uri $uri/ /index.php?path=$uri&$args;
	}

That's it, you're done!