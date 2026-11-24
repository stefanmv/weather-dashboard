import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Weather dashboard',
  description: 'Weather in four places, turned into one AI image.',
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
