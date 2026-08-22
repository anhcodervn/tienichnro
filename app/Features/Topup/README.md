# Topup Feature

Owns the shared game topup domain:

- order pricing, recipients, claiming and state transitions;
- wallet/bank payment orchestration;
- queued fulfillment;
- provider contracts and implementations;
- order realtime events and observers.

Provider HTTP implementations must stay behind `TopupProviderInterface` and must run outside database transactions.
