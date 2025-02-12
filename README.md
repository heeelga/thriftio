<h3>Disclaimer</h3>
This is my first published app, and I am not a professional developer by any means. The app was mainly developed using ChatGPT, and it took quite a bit of effort to build it. I have gone through a lot of trial and error along the way. Please note that the app is still under heavy development, so do NOT solely rely on it for managing your finances!
I'm also not very experienced with GitHub, so please let me know if there's a better way to do something.

<h2>Security</h2>

A Google reCAPTCHA API Key is required to login and to enable the password reset functionality. Registering a new reCAPTCHA key for your website should only take about 2 minutes. Without inserting the reCAPTCHA code into your .env file, you will not be able to login or to use the password reset feature.

To register your reCAPTCHA key, visit:
https://cloud.google.com/security/products/recaptcha

If you prefer not to use reCAPTCHA, use the provided login_without_recaptcha.php file located in the source folder. Simply move it to the app folder and rename it to login.php.Repeat with lost_without_recaptcha.php.

Passwords are encrypted using bcrypt before being stored in the database. However, transaction data is currently unencrypted. I plan to implement full encryption for transactions in the future.

If you plan to expose this app to the internet, PLEASE secure it behind a reverse proxy such as Nginx or Traefik!

<b>Warning:</b>
Incorrect login attempts are logged in the database. After 5 failed attempts, the corresponding IP address will be blocked and redirected to Google or another specified site.

If you plan to share this app with your family or friends, please inform them that their IP address and approx. origin will be logged when logging in. 

<h2>Project Goal</h2>

I created this app to track my expenses in an easy, mobile-friendly manner without too many complicated features. I also wanted the app to support inviting family members.

<h2>Features</h2>
🏦 User Authentication – Secure login and password reset functionality.

📅 Monthly Overview – Scroll through past and future months to track finances.

➕ Add Income/Expenses – Enter financial transactions with date and recurrence options.

🔄 Recurring Transactions – Set up repeating entries with an optional end date.

✅ Mark Entries as Completed/Hidden – Keep track of settled expenses and incomes.

💵 Savings accounts – Create rebookings to/from savings accounts.

📈 Graphs for savings accounts – See your money growing (hopefully) over time

💰 Remaining Balance Calculation – Automatically update the remaining monthly balance.

🛠 Edit/Delete Entries – Modify or remove individual transactions, even in recurring series.

🔍 Search Functionality – Search for upcoming transactions

📊 Data Persistence with MySQL – Transactions are stored in a structured MariaDB database.

🔄 Carry Over Balance to Next Month – Automatically transfer remaining balance.

📂 Dynamic User Tables – Each user has a personalized financial database.

🔍 Responsive UI – Mobile-friendly, intuitive design.

🌙 Dark Mode - We all can't live without it.

👋 Swipe Functionality – Swipe from month to month with a fingertip.

📋 Backup System – Create backups of user-specific tables and sub-tables.

🚀 Quick Setup – Up and running in 5 simple steps

<h3>Shortcuts</h3>
🏠 Navigation

    Ctrl + H → Go to Home
    Ctrl + Left/Right Arrow → Swipe through months

📦 Bulk Actions

    Ctrl + Space → Activate Bulk Mode
    Ctrl + A     → Hide selected entries
    Del          → Delete selected entries

🔄 Transfers & Transactions

    Ctrl + U → Open Transfer Overlay
    Ctrl + B → Open Transaction Overlay

💾 Saving

    Ctrl + S → Save


<h2>To be included in v0.2</h2>
📅 Exact Booking Dates for rebooking entries

🔒 Encrypted booking descriptions

✊ (More) power to the people – Change settings after the container has been deployed    

<h3>Translation Status</h3>

The app was originally developed in German, but approximately 70-80% of the content has been translated into English (set LANGUAGE to en or de in your compose.yml accordingly).

<h2>Prerequisites</h2>
- Docker compose installed <br>
- Google recaptcha Websitekey if you want to use the hardened "Lost password" functionality

<h1>Quick Setup</h1>

1. Clone the project repository.
2. Copy the .env-example file by running


```bash
cp .env-exampe .env
```



3. Edit the <b>.env</b> file by your needs. You mainly want to configure the passwords for the DB access and the app credentials. <br>
<b>You have to change your user password after the initial login.</b>

```bash
nano .env
```



```yaml
MYSQL_USER=finance_user
MYSQL_PASSWORD=SUPERSECRETPASSWORD
MYSQL_ROOT_PASSWORD=SERSECRETROOTPASSWORD      
APP_USER=financeuser                               
APP_PASSWORD=userpassword                           
APP_FIRSTNAME=financename                        
APP_EMAIL=finance@finance.xyz                         
PUSHOVER_USER_TOKEN=YOURPUSHOVERUSERTOKEN   
PUSHOVER_APP_TOKEN=YOURPUSHOVERAPPTOKEN 
SMTP_HOST=SMTP.MAILPROVIDER.xyz                     
SMTP_USER=thriftio@userdomain.xyz                       
SMTP_PASSWORD=YOURSMTPPASSWORD               
SMTP_SENDER=thriftio@userdomain.xyz                          
SMTP_SENDER_NAME=ThriftioUSER                  
SMTP_REPLYTO=thriftio@userdomain.xyz               
SMTP_REPLYTO_NAME=ThriftioUSER
RECAPTCHA_SITEKEY=YOURKEYFROMGOOGLE
RECAPTCHA_SECRET=YOURSECRETFROMGOOGLE                                 
```


4. run docker compose up -d (or docker-compose up -d if you're not running the latest version of docker compose).

```yaml
docker compose up -d
```

5. Go to http://your-instance:8080/  -> Use the configured app credentials of your compose file to login.
