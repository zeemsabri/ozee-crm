import { useState } from 'react';
import { Nope } from '../ds';
import 'no-such-package-xyz';

export function Probe({ ready }) {
    if (!ready) return null;
    const [x, setX] = useState(0);
    return <div>{x}</div>;
}
