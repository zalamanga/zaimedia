import argparse
import base64
import os
import secrets
from pathlib import Path


def main():
    parser = argparse.ArgumentParser(description='Create private settings for a new local Zaimedia Docker database.')
    parser.add_argument('--port', type=int, default=18090)
    parser.add_argument('--demo', action='store_true', help='Explicitly enable local demo auto-paid checkout; no payment gateway is used.')
    parser.add_argument('--output', type=Path, default=Path(__file__).resolve().parents[1] / '.env')
    args = parser.parse_args()
    if not 1024 <= args.port <= 65535:
        parser.error('Use a port between 1024 and 65535.')
    output = args.output.resolve()
    output.parent.mkdir(parents=True, exist_ok=True)
    settings = {
        'APP_PORT': str(args.port),
        'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
        'APP_URL': f'http://127.0.0.1:{args.port}',
        'DB_DATABASE': 'zaimedia',
        'DB_USERNAME': 'zaimedia',
        'DB_PASSWORD': secrets.token_hex(24),
        'MYSQL_ROOT_PASSWORD': secrets.token_hex(24),
        'CHECKOUT_DEMO_MODE': str(args.demo).lower(),
        'CHECKOUT_AUTO_PAID': str(args.demo).lower(),
    }
    try:
        descriptor = os.open(output, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    except FileExistsError:
        parser.error(f'{output} already exists; existing settings were preserved.')
    with os.fdopen(descriptor, 'w', encoding='utf-8', newline='\n') as stream:
        stream.write('\n'.join(f'{key}={value}' for key, value in settings.items()) + '\n')
    print(f'Created {output}; keep this file private and reuse it to retain database access and APP_KEY.')
    print('Payment mode: ' + ('local demo auto-paid' if args.demo else 'pending orders; auto-paid disabled'))


if __name__ == '__main__':
    main()
