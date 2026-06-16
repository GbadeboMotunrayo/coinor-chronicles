"""
Heaven & Gate calculator.

Between every two Gates are 10 Heavens. The journey is infinite:
once a coin crosses Gate N, it starts climbing toward Gate N+1.

Heaven position = how many tenths of the way between the current gate floor
                  and the next gate ceiling the price sits.
"""
from config import GOLDEN_GATES


def get_gate_and_heaven(ticker: str, price: float) -> tuple[int, int]:
    """
    Returns (gate_number, heaven_number) for the given price.
    Gate 1 is the first milestone. Heaven 1..10 within each gate.
    If price exceeds all defined gates, extrapolate the last gate pair.
    """
    gates = GOLDEN_GATES.get(ticker)
    if not gates:
        return (1, 1)

    # Find which gate the price currently sits below
    for i, gate_price in enumerate(gates):
        if price < gate_price:
            # Price is between gates[i-1] (or 0) and gates[i]
            floor = gates[i - 1] if i > 0 else 0.0
            ceiling = gate_price
            gate_num = i + 1   # gates are 1-indexed
            heaven_num = _heaven_in_range(price, floor, ceiling)
            return (gate_num, heaven_num)

    # Price is beyond all defined gates — keep extending
    last_gate = len(gates)
    floor = gates[-2] if len(gates) >= 2 else gates[-1] * 0.5
    ceiling = gates[-1]
    gap = ceiling - floor
    overflow = price - ceiling
    extra_gates = int(overflow / gap)
    gate_num = last_gate + extra_gates + 1
    new_floor = ceiling + extra_gates * gap
    new_ceiling = new_floor + gap
    heaven_num = _heaven_in_range(price, new_floor, new_ceiling)
    return (gate_num, heaven_num)


def _heaven_in_range(price: float, floor: float, ceiling: float) -> int:
    gap = ceiling - floor
    if gap <= 0:
        return 1
    position = (price - floor) / gap  # 0.0 → 1.0
    heaven = min(10, max(1, int(position * 10) + 1))
    return heaven


def describe_position(ticker: str, price: float) -> str:
    gate, heaven = get_gate_and_heaven(ticker, price)
    ordinal = _ordinal(heaven)
    return f'the {ordinal} Heaven of Gate {gate}'


def _ordinal(n: int) -> str:
    suffixes = {1: 'First', 2: 'Second', 3: 'Third', 4: 'Fourth', 5: 'Fifth',
                6: 'Sixth', 7: 'Seventh', 8: 'Eighth', 9: 'Ninth', 10: 'Tenth'}
    return suffixes.get(n, str(n) + 'th')


if __name__ == '__main__':
    tests = [
        ('BTC', 95_000),
        ('ETH', 3_200),
        ('DOGE', 0.18),
        ('PEPE', 0.000012),
    ]
    for ticker, price in tests:
        g, h = get_gate_and_heaven(ticker, price)
        print(f'{ticker} @ ${price}: Gate {g}, Heaven {h} — {describe_position(ticker, price)}')
