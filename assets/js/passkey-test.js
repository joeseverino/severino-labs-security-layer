(function () {
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

    function setStatus(message, type) {
        var status = document.getElementById('sl-passkey-test-status');

        if (!status) {
            return;
        }

        status.textContent = message;
        status.className = 'description';

        if (type) {
            status.classList.add('sl-passkey-test-' + type);
        }
    }

    async function runUsernamelessPasskeyTest() {
        var button = document.getElementById('sl-test-passkey');

        if (!button) {
            return;
        }

        if (
            window.PublicKeyCredential === undefined ||
            navigator.credentials === undefined ||
            typeof navigator.credentials.get !== 'function'
        ) {
            setStatus('This browser does not support passkeys.', 'error');
            return;
        }

        if (typeof slSecurityAdmin === 'undefined') {
            setStatus('Admin settings were not loaded.', 'error');
            return;
        }

        button.disabled = true;
        setStatus('Starting usernameless passkey test...', '');

        try {
            var startUrl = slSecurityAdmin.ajaxUrl + '?action=wwa_auth_start&type=auth&usernameless=true';

            var startResponse = await fetch(startUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            var startText = await startResponse.text();
            var options;

            try {
                options = JSON.parse(startText);
            } catch (error) {
                throw new Error('Could not parse WP-WebAuthn start response.');
            }

            if (!options || typeof options !== 'object') {
                throw new Error('Invalid WP-WebAuthn options returned.');
            }

            if (!options.challenge) {
                throw new Error('Missing passkey challenge.');
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
                publicKey: options
            });

            if (!credential) {
                throw new Error('No passkey credential returned.');
            }

            var payload = {
                id: credential.id,
                type: credential.type,
                rawId: arrayBufferToBase64(credential.rawId),
                response: {
                    authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
                    clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
                    signature: arrayBufferToBase64(credential.response.signature),
                    userHandle: credential.response.userHandle
                        ? arrayBufferToBase64(credential.response.userHandle)
                        : null
                }
            };

            var verifyFormData = new URLSearchParams();

            verifyFormData.append('data', window.btoa(JSON.stringify(payload)));
            verifyFormData.append('type', 'auth');
            verifyFormData.append('remember', 'false');
            verifyFormData.append('clientid', clientID);

            setStatus('Verifying passkey with WP-WebAuthn...', '');

            var verifyResponse = await fetch(slSecurityAdmin.ajaxUrl + '?action=wwa_auth', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: verifyFormData.toString()
            });

            var verifyText = (await verifyResponse.text()).trim();

            if (verifyText !== 'true') {
                throw new Error('WP-WebAuthn did not verify the passkey.');
            }

            setStatus('Passkey verified. Unlocking setting...', 'success');

            var markFormData = new URLSearchParams();

            markFormData.append('action', 'sl_mark_passkey_usernameless_verified');
            markFormData.append('nonce', slSecurityAdmin.passkeyTestNonce);

            var markResponse = await fetch(slSecurityAdmin.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: markFormData.toString()
            });

            var markResult = await markResponse.json();

            if (!markResult || !markResult.success) {
                var message = markResult && markResult.data && markResult.data.message
                    ? markResult.data.message
                    : 'Could not save passkey verification status.';

                throw new Error(message);
            }

            setStatus('Usernameless passkey authentication verified. Reloading...', 'success');
            window.location.reload();
        } catch (error) {
            console.warn(error);

            var message = error && error.message
                ? error.message
                : 'Usernameless passkey test failed.';

            setStatus(message, 'error');
        } finally {
            button.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var button = document.getElementById('sl-test-passkey');

        if (!button) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            runUsernamelessPasskeyTest();
        });
    });
})();