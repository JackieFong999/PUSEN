#!/usr/bin/env python3
"""Encrypt a plain .pdf into a password-protected .pdf (AES-256, password to open).

Mirrors scripts/encrypt_xlsx.py: called from PHP via exec(), exits 0 on success.
The PDF is encrypted with AES-256 (R=6); the user password (required to open)
and owner password are both set to the supplied password.

Usage: encrypt_pdf.py <input.pdf> <output.pdf> <password>
"""
import sys

import pikepdf


def main():
    if len(sys.argv) != 4:
        print("usage: encrypt_pdf.py <input.pdf> <output.pdf> <password>", file=sys.stderr)
        return 2

    src, dst, password = sys.argv[1], sys.argv[2], sys.argv[3]

    with pikepdf.open(src) as pdf:
        pdf.save(
            dst,
            encryption=pikepdf.Encryption(
                owner=password,
                user=password,
                R=6,       # AES-256
                aes=True,
            ),
        )

    return 0


if __name__ == "__main__":
    sys.exit(main())
