FROM php:5.6

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
            smbclient \
            samba

# grab gosu for easy step-down from root
ENV GOSU_VERSION 1.7
RUN set -x \
	&& curl -L -o /usr/local/bin/gosu "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$(dpkg --print-architecture)" \
	&& curl -L -o /usr/local/bin/gosu.asc "https://github.com/tianon/gosu/releases/download/$GOSU_VERSION/gosu-$(dpkg --print-architecture).asc" \
	&& export GNUPGHOME="$(mktemp -d)" \
	&& gpg --keyserver ha.pool.sks-keyservers.net --recv-keys B42F6819007F00F88E364FD4036A9C25BF357DD4 \
	&& gpg --batch --verify /usr/local/bin/gosu.asc /usr/local/bin/gosu \
	&& rm -r "$GNUPGHOME" /usr/local/bin/gosu.asc \
	&& chmod +x /usr/local/bin/gosu \
	&& gosu nobody true

RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

RUN useradd --shell /bin/bash -m samba
RUN echo 'password' | tee - | smbpasswd -a -s samba
RUN mkdir -p /home/samba/samba-test
RUN chown -R samba:samba /home/samba/samba-test

ADD docker/smb.conf /root/smb.conf
RUN cat /root/smb.conf >> /etc/samba/smb.conf

VOLUME "/test"
WORKDIR "/test"

ADD docker/entrypoint.sh /root/entrypoint.sh
ADD docker/php.sh /root/php.sh

ENTRYPOINT ["/root/entrypoint.sh"]
CMD ["bin/phpunit"]
