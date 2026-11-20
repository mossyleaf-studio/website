const SOCIAL_HOSTS = ['instagram.com', 'www.instagram.com'];

export function relOf(url) {
    return URL.canParse(url) && SOCIAL_HOSTS.includes(new URL(url).hostname) ? 'me' : undefined;
}
