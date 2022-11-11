if [ -f ~/.profile_personal ]; then
    . ~/.profile_personal
fi

git config --global alias.st status
git config --global alias.ci commit
git config --global alias.co checkout
git config --global alias.br branch
git config --global color.branch auto
git config --global color.diff auto
git config --global color.interactive auto
git config --global color.status auto
git config --global push.default current
git config --global --add safe.directory /var/www/app
git config --global init.defaultBranch master

alias ll="ls -al"
alias pfull="gpc && fix && punit && git st"
alias gap="gpcu && git add . && pfull"
alias gitnb=git_branch_new
alias gitmb=git_branch_merge
alias gaf="clear && git add . && fix && git st"

git_branch_new() {
    if [[ -n "$1" && "$1" != ' ' && -n "$2" && "$2" != ' ' ]]; then
        FROM="$1"
        TO="$2"
    else
        FROM='master'
        TO="$1"
    fi

    git co "${FROM}" && git pull && git push origin "${FROM}":"${TO}" && git co "${TO}" && git pull

    if [[ -e 'composer.json' ]]; then
        composer install
    fi

    git st
}

git_branch_merge() {
    if [[ -n "$1" && "$1" != ' ' ]]; then
        FROM="$1"
    else
        FROM='master'
    fi

    if [[ -n "$2" && "$2" != ' ' ]]; then
        TO="$2"
    else
        TO=$(git rev-parse --abbrev-ref HEAD)
    fi

    git co "${TO}" && git pull && git pull origin "${FROM}" --rebase
}

gdiff() {
    git diff -w "$@"
}

gpc() {
    clear && git pull && composer install "$@" && git st
}

gpcu() {
    clear && git pull && composer update "$@" && git st
}

fix() {
    if [[ -e "${PWD}/vendor/bin/php-cs-fixer" ]]; then
        PCFPATH="${PWD}/vendor/bin/php-cs-fixer"
    else
        echo -e '\e[31m\e[1m[ nu exista php cs fixer ]\e[21m\e[0m'
        return 0
    fi

    echo -e "\e[33m[\e[32m\e[1m ${PCFPATH} \e[21m\e[33m]\e[0m"
    php -d memory_limit=-1 "${PCFPATH}" fix "$@"
}

punit() {
    if [[ -e "${PWD}/vendor/bin/simple-phpunit" ]]; then
        PCFPATH="${PWD}/vendor/bin/simple-phpunit"
    else
        echo -e '\e[31m\e[1m[ nu exista phpunit ]\e[21m\e[0m'
        return 0
    fi

    echo -e "\e[33m[\e[32m\e[1m ${PCFPATH} \e[21m\e[33m]\e[0m"
    php -d memory_limit=-1 "${PCFPATH}" "$@"
}
