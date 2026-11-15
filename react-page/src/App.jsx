import { useState } from 'react';

export const GREETING = 'Hello from React';

export default function App() {
  const [count, setCount] = useState(0);

  return (
    <main className="page">
      <h1>{GREETING}</h1>
      <p>This is a simple React page.</p>
      <button type="button" onClick={() => setCount((n) => n + 1)}>
        Clicked {count} {count === 1 ? 'time' : 'times'}
      </button>
    </main>
  );
}
