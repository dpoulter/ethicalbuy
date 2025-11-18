# warmedals
Searchable database of War Medals that have been sold at auction. This is implemented using a LAMP stack and a Java application to scrape medals from online sites.  See a working example here: http://warmedals.duckdns.org

medals.sql
------
Table creation script 

medalscraper.jar
-----------------
Java JAR file -  application to scrape Auction websites and insert medals data into the database.

Parameters: 
1. Auction Number
2. No of records

Example:

java -jar medalscraper.java 670 800
