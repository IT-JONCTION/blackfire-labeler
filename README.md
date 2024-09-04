# Blackfire Labeler Task Runner

## Overview

This repository provides a utility to facilitate the labeling of transactions using Blackfire and manage their associated data in Redis. The primary components include a task runner, a Blackfire labeler, and a Redis configuration class. The setup is intentionally small and siloed to handle only the limited functionality required for Blackfire profiling and Redis data management. This design is deliberate because the code is intended to be called from an `auto_prepend` file. By keeping it isolated, we avoid potential conflicts with the `RedisConfig` class or other configurations used in different parts of the project or other projects.

## Logging and Profiling

- **BlackfireLabeler** offers methods for:
  - Generating unique request hashes.
  - Logging request details and included files to Redis.
  - Setting transaction names for Blackfire profiling.
  - Archiving and managing logs.

## Project Structure

```plaintext
.
├── src
│   ├── ItJonction
│   │   └── BlackfireLabeler
│   │       ├── BlackfireLabelerController.php
│   │       ├── CommonTaskRunner.php
│   │       ├── BlackfireLabeler.php
│   │       ├── RedisConfig.php
│   │       └── task_blackfireArchiver.php
├── tests
│   └── BlackfireIntegration
│       └── BlackfireTest.php
├── build
│   └── logs
│   └── coverage-html
├── redisValues.php.example
├── composer.json
├── phpunit.xml
└── README.md
```

### Installation

1. **Clone the Repository:**

   ```bash
   git clone https://github.com/IT-JONCTION/blackfire-labeler.git
   cd blackfire-labeler
   ```

2. **Install Composer Dependencies:**

   ```bash
   composer install
   ```

3. **Set Up Redis Configuration:**

   The repository includes a `redisValues.php.example` file, which serves as a template for your Redis configuration and includes options for the host, port, authentication, and database selection. 
   
   To set up your environment:

   1. Copy the example file to `redisValues.php`:

      ```bash
      cp redisValues.php.example redisValues.php
      ```

   2. Open the newly created `redisValues.php` file in a text editor and configure your Redis connection settings according to your environment:

      ```php
      <?php
      $scopedRedisEnvValues = [
          'REDIS_HOST' => '127.0.0.1',
          'REDIS_PORT' => 6379,
          'REDIS_USERNAME' => 'default',
          'REDIS_PASSWORD' => '',
          'REDIS_DB' => 0,
          'REDIS_SECURE' => false,
      ];
      ```

   > **Note:** `redisValues.php` should contain your actual configuration values and should **not** be committed to version control. The `.gitignore` file has been configured to exclude this file.

4. **Move the Entire Project to `/usr/local/blackfire-labeler/`:**

   Once you've set up the Redis configuration and installed Composer, exit the project directory and move the entire folder to `/usr/local/blackfire-labeler/`.

   ```bash
   cd ..
   sudo mv blackfire-labeler /usr/local/blackfire-labeler
   ```

   Ensure the permissions are correctly set:

   ```bash
   sudo chown -R root:www-data /usr/local/blackfire-labeler
   sudo chmod -R 755 /usr/local/blackfire-labeler
   ```

5. **Configure `php.ini` for `auto_prepend_file`:**

   Add the following line to your `php.ini` file to ensure the project is auto-prepended:

```ini
auto_prepend_file = /usr/local/blackfire-labeler/src/ItJonction/BlackfireLabeler/BlackfireLabelerController.php
```

6. **Create a Cron Job for Archiving Blackfire Logs:**

   To regularly run the `task_blackfireArchiver.php` script and archive Blackfire logs, you can set up a cron job. Here's how:

   1. Open your terminal and run the following command to edit the crontab file:

      ```bash
      crontab -e
      ```

   2. In the crontab file, add a new line to specify the schedule for running the `task_blackfireArchiver.php` script. For example, to run the script every day at 2:00 AM, add the following line:

      ```plaintext
      0 2 * * * php /usr/local/blackfire-labeler/src/ItJonction/BlackfireLabeler/task_blackfireArchiver.php
      ```

      This line specifies that the script should be executed at minute 0, hour 2, every day, every month, and every day of the week.

   3. Save the crontab file and exit the editor.

   The cron job is now set up to run the `task_blackfireArchiver.php` script at the specified schedule. This will ensure that your Blackfire logs are regularly archived and managed.

## Contributing

Contributions to this repository are welcome. Please fork the repository and create a pull request for any feature enhancements or bug fixes. Ensure that your code adheres to the project's coding standards and includes appropriate tests.

## Running local tests
If you want to run the tests locally, make sure you have a Redis instance running. You can either install Redis directly on your machine or use Docker. For simplicity, we have assumed that you have Redis and PHP 8.2 installed. Feel free to create an issue requesting docker, or better still add a PR.

- **Using Local Redis Installation**:
   1. Start your local Redis server:
      ```bash
      redis-server
      ```
   2. Run PHPUnit tests:
      ```bash
      ./vendor/bin/phpunit --configuration phpunit.xml
      ```

## License

This project is licensed under the MIT License. See the `LICENSE` file for more details.

## Support

If you encounter any issues or have questions, please open an issue in the repository or contact the maintainers.
