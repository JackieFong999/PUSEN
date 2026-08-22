#!/usr/bin/env python3
"""Encrypt a plain .xlsx into an OOXML Agile-Encrypted .xlsx (Excel password-to-open).

Usage: encrypt_xlsx.py <input.xlsx> <output.xlsx> <password>
"""
import sys
from msoffcrypto.format.ooxml import OOXMLFile


def main():
    if len(sys.argv) != 4:
        print("usage: encrypt_xlsx.py <input.xlsx> <output.xlsx> <password>", file=sys.stderr)
        return 2

    src, dst, password = sys.argv[1], sys.argv[2], sys.argv[3]

    with open(src, "rb") as fin:
        ooxml = OOXMLFile(fin)
        with open(dst, "wb") as fout:
            ooxml.encrypt(password, fout)

    return 0


if __name__ == "__main__":
    sys.exit(main())
