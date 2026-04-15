# SeAT Manual PAP

![Latest Stable Version](https://img.shields.io/packagist/v/kaho88/seat-manualpap?style=flat-square)
![Downloads](https://img.shields.io/packagist/dt/kaho88/seat-manualpap?style=flat-square)
![License](https://img.shields.io/packagist/l/kaho88/seat-manualpap?style=flat-square)
![SeAT Version](https://img.shields.io/badge/SeAT-5.x-blue?style=flat-square)

Manual PAP insertion plugin for SeAT. VIBE CODE!

This plugin allows you to add PAP entries manually and is designed to work together with the SeAT Calendar ecosystem.

## Features

- Manual PAP insertion  
- SeAT 5 compatible  
- Works together with seat-calendar  
- API-ready structure for future integrations
- Supports AllianceAuth FAT (Fleet Activity Tracking) imports

### Advanced Features

- Import FATs from AllianceAuth (copy & paste) and automatically generate OPs and PAPs for monthly tracking  
- Inactive user detection – identify players with no FAT activity for 3 months  
- Monthly PAP reports – overview of all SeAT PAPs in a structured report view  

## Requirements

- PHP / Composer environment compatible with SeAT 5
- `eveseat/web:^5`
- `eveseat/eveapi:^5`
- `eveseat/services:^5`

## Installation

Install via your .env file: kaho88/seat-manualpap
