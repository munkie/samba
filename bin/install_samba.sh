#!/bin/sh
sudo apt-get update -qq
sudo apt-get install -y smbclient samba

echo 'password' | tee - | sudo smbpasswd -a -s $USER

mkdir -p ~/samba-test

echo '[samba-test]' | sudo tee -a /etc/samba/smb.conf
echo "path = $HOME/samba-test" | sudo tee -a /etc/samba/smb.conf
echo 'available = yes' | sudo tee -a /etc/samba/smb.conf
echo "valid users = $USER" | sudo tee -a /etc/samba/smb.conf
echo 'read only = no' | sudo tee -a /etc/samba/smb.conf
echo 'browseable = yes' | sudo tee -a /etc/samba/smb.conf
echo 'public = yes' | sudo tee -a /etc/samba/smb.conf
echo 'writable = yes' | sudo tee -a /etc/samba/smb.conf
echo 'guest ok = no' | sudo tee -a /etc/samba/smb.conf

sudo restart smbd
testparm -s