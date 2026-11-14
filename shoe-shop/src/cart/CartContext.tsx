import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import type { CartItem, Order } from '../types';
import { addToCart, cartCount, removeFromCart, setLineQuantity } from './cart';

/*
 * Cart and the last order live in React state only. Browser storage and cookies
 * are blocked in the preview, so a page refresh starts a fresh cart.
 */

interface CartContextValue {
  items: CartItem[];
  count: number;
  lastOrder: Order | null;
  add(line: Omit<CartItem, 'quantity'>, quantity?: number): void;
  setQuantity(key: string, quantity: number): void;
  remove(key: string): void;
  clear(): void;
  placeOrder(order: Order): void;
}

const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([]);
  const [lastOrder, setLastOrder] = useState<Order | null>(null);

  const add = useCallback((line: Omit<CartItem, 'quantity'>, quantity = 1) => {
    setItems((current) => addToCart(current, line, quantity));
  }, []);

  const setQuantity = useCallback((key: string, quantity: number) => {
    setItems((current) => setLineQuantity(current, key, quantity));
  }, []);

  const remove = useCallback((key: string) => {
    setItems((current) => removeFromCart(current, key));
  }, []);

  const clear = useCallback(() => setItems([]), []);

  const placeOrder = useCallback((order: Order) => setLastOrder(order), []);

  const value = useMemo<CartContextValue>(
    () => ({
      items,
      count: cartCount(items),
      lastOrder,
      add,
      setQuantity,
      remove,
      clear,
      placeOrder,
    }),
    [items, lastOrder, add, setQuantity, remove, clear, placeOrder],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used inside <CartProvider>');
  }
  return context;
}
