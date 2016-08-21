FROM php:5.6

RUN apt-get update && \
    apt-get install -y \
            smbclient \
            samba

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

RUN echo 'password' | tee - | smbpasswd -a -s root

RUN mkdir -p /root/samba-test

ADD docker/entrypoint.sh /root/entrypoint.sh
ADD docker/smb.conf /root/smb.conf

RUN cat /root/smb.conf >> /etc/samba/smb.conf

VOLUME "/test"
WORKDIR "/test"

ENTRYPOINT ["/root/entrypoint.sh"]
CMD ["bin/phpunit"]
