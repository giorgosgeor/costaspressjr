@echo off

start "APP SERVER" cmd /k "C:\Projects\php\php.exe -S localhost:8000 -t C:\Projects\costaspressjr\public"

start "PHPMYADMIN" cmd /k "C:\Projects\php\php.exe -S localhost:8080 -t C:\Projects\phpmyadmin"
