"""
Fetch live prices from CoinGecko for all 21 companions.
Returns a dict keyed by ticker symbol.
"""
import requests
from config import COINGECKO_BASE, COIN_MAP, CHARACTER_MAP

_TIMEOUT = 20


def fetch_prices() -> dict[str, dict]:
    ids = ','.join(COIN_MAP.keys())
    url = (
        f'{COINGECKO_BASE}/simple/price'
        f'?ids={ids}'
        f'&vs_currencies=usd'
        f'&include_24hr_change=true'
        f'&include_24hr_vol=true'
        f'&include_market_cap=true'
    )
    resp = requests.get(url, timeout=_TIMEOUT, headers={'Accept': 'application/json'})
    resp.raise_for_status()
    raw = resp.json()

    prices: dict[str, dict] = {}
    for cg_id, ticker in COIN_MAP.items():
        if cg_id not in raw:
            continue
        d = raw[cg_id]
        change = d.get('usd_24h_change') or 0.0
        prices[ticker] = {
            'price':      d['usd'],
            'change_24h': round(change, 4),
            'volume':     d.get('usd_24h_vol', 0),
            'market_cap': d.get('usd_market_cap', 0),
            'gold_units': round(abs(change), 2),
            'direction':  'heavier' if change >= 0 else 'lighter',
            'character':  CHARACTER_MAP.get(ticker, ticker),
        }
    return prices


def determine_market_condition(prices: dict[str, dict]) -> str:
    btc = prices.get('BTC', {}).get('change_24h', 0)
    eth = prices.get('ETH', {}).get('change_24h', 0)
    avg = (btc + eth) / 2
    if avg > 0.5:
        return 'golden_season'
    if avg < -0.5:
        return 'dark_siege'
    return 'waiting_plains'


if __name__ == '__main__':
    import json
    p = fetch_prices()
    print(json.dumps(p, indent=2))
    print('Condition:', determine_market_condition(p))
