(function () {
    var passkeyRequestActive = false;

    function base64urlToBase64(input) {
        input = input.replace(/=/g, '').replace(/-/g, '+').replace(/_/g, '/');

        var pad = input.length % 4;

        if (pad) {
            if (pad === 1) {
                throw new Error('Invalid base64url string.');
            }

            input += new Array(5 - pad).join('=');
        }

        return input;
    }

    function arrayBufferToBase64(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';

        for (var i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }

        return window.btoa(binary);
    }

    function base64ToBase64url(input) {
        return input
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=/g, '');
    }

    function setStatus(message, type) {
        var el = document.getElementById('jp-status');

        if (!el) {
            return;
        }

        el.textContent = message;
        el.className = 'jp-status' + (type ? ' jp-' + type : '');
    }

    function getPasskeyButton() {
        return document.getElementById('jp-passkey-btn');
    }

    function setButtonLoading(isLoading) {
        var button = getPasskeyButton();

        if (!button) {
            return;
        }

        button.disabled = isLoading;
        button.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    async function startPasskeyLogin() {
        if (passkeyRequestActive) {
            setStatus('A passkey request is already pending. Finish or cancel it first.', 'error');
            return;
        }

        var button = getPasskeyButton();

        if (!button) {
            return;
        }

        passkeyRequestActive = true;
        setButtonLoading(true);
        setStatus('Starting passkey authentication...', '');

        try {
            if (typeof slPasskeyLogin === 'undefined') {
                throw new Error('Passkey login settings were not loaded.');
            }

            if (
                window.PublicKeyCredential === undefined ||
                navigator.credentials === undefined ||
                typeof navigator.credentials.get !== 'function'
            ) {
                throw new Error('This browser does not support passkeys.');
            }

            var startUrl = slPasskeyLogin.ajaxUrl + '?action=wwa_auth_start&type=auth&usernameless=true';

            var startResponse = await fetch(startUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            var startText = await startResponse.text();
            var options;

            try {
                options = JSON.parse(startText);
            } catch (err) {
                throw new Error('Could not parse auth start response.');
            }

            if (!options || typeof options !== 'object') {
                throw new Error('Invalid auth options returned.');
            }

            if (!options.challenge) {
                throw new Error('Missing challenge in auth options.');
            }

            options.challenge = Uint8Array.from(
                window.atob(base64urlToBase64(options.challenge)),
                function (c) {
                    return c.charCodeAt(0);
                }
            );

            if (Array.isArray(options.allowCredentials)) {
                options.allowCredentials = options.allowCredentials.map(function (item) {
                    item.id = Uint8Array.from(
                        window.atob(base64urlToBase64(item.id)),
                        function (c) {
                            return c.charCodeAt(0);
                        }
                    );

                    return item;
                });
            }

            var clientID = options.clientID;
            delete options.clientID;

            setStatus('Waiting for passkey prompt...', '');

            var credential = await navigator.credentials.get({
                publicKey: options,
                mediation: 'optional'
            });

            if (!credential) {
                throw new Error('No credential returned.');
            }

            var payload = {
                id: credential.id,
                type: credential.type,
                rawId: base64ToBase64url(arrayBufferToBase64(credential.rawId)),
                response: {
                    authenticatorData: base64ToBase64url(arrayBufferToBase64(credential.response.authenticatorData)),
                    clientDataJSON: base64ToBase64url(arrayBufferToBase64(credential.response.clientDataJSON)),
                    signature: base64ToBase64url(arrayBufferToBase64(credential.response.signature)),
                    userHandle: credential.response.userHandle
                        ? base64ToBase64url(arrayBufferToBase64(credential.response.userHandle))
                        : null
                }
            };

            var formData = new URLSearchParams();

            formData.append('data', window.btoa(JSON.stringify(payload)));
            formData.append('type', 'auth');
            formData.append('remember', 'false');
            formData.append('clientid', clientID || '');

            setStatus('Verifying passkey...', '');

            var verifyResponse = await fetch(slPasskeyLogin.ajaxUrl + '?action=wwa_auth', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData.toString()
            });

            var verifyText = (await verifyResponse.text()).trim();

            if (verifyText === 'true') {
                setStatus('Authentication successful. Redirecting...', 'success');
                window.location.href = slPasskeyLogin.redirectUrl;
                return;
            }

            throw new Error('Authentication failed.');
        } catch (error) {
            console.warn(error);

            var message = error && error.message
                ? error.message
                : 'Passkey authentication failed.';

            if (
                error &&
                (
                    error.name === 'AbortError' ||
                    error.name === 'NotAllowedError'
                )
            ) {
                message = 'Passkey authentication was canceled or timed out.';
            }

            if (
                error &&
                error.message &&
                error.message.toLowerCase().indexOf('pending') !== -1
            ) {
                message = 'A passkey request is already pending. Close the Windows Hello prompt or restart the browser.';
            }

            setStatus(message, 'error');
        } finally {
            passkeyRequestActive = false;
            setButtonLoading(false);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var button = getPasskeyButton();

        if (!button) {
            return;
        }

        if (
            window.PublicKeyCredential === undefined ||
            navigator.credentials === undefined ||
            typeof navigator.credentials.get !== 'function'
        ) {
            button.disabled = true;
            setStatus('This browser does not support passkeys.', 'error');
            return;
        }

        button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            startPasskeyLogin();
        });
    });
})();